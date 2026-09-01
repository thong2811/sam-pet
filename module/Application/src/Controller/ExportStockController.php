<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\ExportStock;
use Application\Model\Product;
use Application\Service\CommonService;
use Application\Service\GoogleSheetsService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ExportStockController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }

    public function addAction()
    {
        $productModel = new Product();
        $productList = $productModel->getData();
        return new ViewModel(['productList' => $productList]);
    }

    public function doAddAction()
    {
        try {
            $request  = $this->getRequest();
            $postData = $request->getPost()->toArray();

            $exportStockModel = new ExportStock();
            $exportStockModel->doAdd($postData);

            return new JsonModel(['success' => true, 'message' => 'Thêm thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function editAction()
    {
        $date = $this->params()->fromRoute('date', '');

        $exportStockModel = new ExportStock();
        $exportStockList = $exportStockModel->getDataByKeyTypeDate('date', $date);

        $productModel = new Product();
        $productList = $productModel->getData();

        return new ViewModel(['date' => $date, 'exportStockList' => $exportStockList, 'productList' => $productList]);
    }

    public function doEditAction()
    {
        try {
            $request  = $this->getRequest();
            $postData = $request->getPost()->toArray();
            $date     = $postData['date'][0] ?? ($postData['date'] ?? '');

            $exportStockModel = new ExportStock();
            $exportStockModel->doEdit($postData);

            $redirectUrl = !empty($date)
                ? $this->url()->fromRoute('exportStock', ['action' => 'edit', 'date' => $date])
                : $this->url()->fromRoute('exportStock');

            return new JsonModel(['success' => true, 'message' => 'Cập nhật thành công!', 'redirectUrl' => $redirectUrl]);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function doDeleteAction()
    {
        try {
            $request = $this->getRequest();
            $body = $request->getContent();
            $data = json_decode($body, true);

            if (!isset($data['id'])) {
                return new JsonModel([
                    'success' => false,
                    'message' => 'ID không được cung cấp.',
                ]);
            }

            $id = $data['id'];
            $exportStockModel = new ExportStock();
            $exportStockModel->deleteRow($id);

            return new JsonModel([
                'success' => true,
                'message' => 'Xóa thành công!',
            ]);
        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $request = $this->getRequest();
            $postData = $request->getPost();

            $exportStockModel = new ExportStock();
            $data = $exportStockModel->getDataToView();

            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);

        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch dữ liệu từ Google Sheets và trả về danh sách bản ghi mới chưa có trong CSV.
     * Client dùng kết quả này để hiển thị preview trước khi xác nhận đồng bộ.
     *
     * POST /export-stock/sync-preview
     * Body: { "date": "27-12-2025" }  — date là optional, nếu thiếu thì lấy toàn bộ
     */
    public function syncPreviewAction()
    {
        try {
            $request = $this->getRequest();
            $body    = $request->getContent();
            $input   = json_decode($body, true) ?? [];
            $date    = trim((string) ($input['date'] ?? ''));

            $sheetsService   = new GoogleSheetsService();
            $allRows         = $sheetsService->fetchAll();

            // Filter theo ngày nếu có
            if ($date !== '') {
                $rows = array_values(array_filter($allRows, function (array $row) use ($date): bool {
                    return ($row['date'] ?? '') === $date;
                }));
            } else {
                $rows = $allRows;
            }

            $exportStockModel = new ExportStock();
            $newRows          = $exportStockModel->filterNewRows($rows);
            $skippedCount     = count($rows) - count($newRows);

            return new JsonModel([
                'success'      => true,
                'newRows'      => $newRows,
                'newCount'     => count($newRows),
                'skippedCount' => $skippedCount,
            ]);

        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            CommonService::loggerException()->error($e->getMessage());
            return new JsonModel([
                'success' => false,
                'message' => 'Đã xảy ra lỗi không xác định.',
            ]);
        }
    }

    /**
     * Ghi các bản ghi mới từ Google Sheets vào CSV sau khi người dùng xác nhận.
     * Gọi filterNewRows() lần thứ 2 để chống race condition giữa preview và confirm.
     *
     * POST /export-stock/do-sync
     * Body: { "rows": [ { "id": "...", "date": "...", ... } ] }
     */
    public function doSyncAction()
    {
        try {
            $request = $this->getRequest();
            $body    = $request->getContent();
            $input   = json_decode($body, true) ?? [];
            $rows    = $input['rows'] ?? [];

            if (empty($rows) || !is_array($rows)) {
                return new JsonModel([
                    'success' => false,
                    'message' => 'Không có dữ liệu rows để đồng bộ.',
                ]);
            }

            // Validate từng row — bỏ qua row thiếu field bắt buộc
            $requiredFields = ['id', 'date', 'productId', 'productName', 'quantity'];
            $validRows = array_values(array_filter($rows, function ($row) use ($requiredFields): bool {
                if (!is_array($row)) {
                    return false;
                }
                foreach ($requiredFields as $field) {
                    if (!isset($row[$field]) || $row[$field] === '') {
                        return false;
                    }
                }
                return true;
            }));

            if (empty($validRows)) {
                return new JsonModel([
                    'success' => false,
                    'message' => 'Không có bản ghi hợp lệ để đồng bộ.',
                ]);
            }

            // Gọi filterNewRows() lần 2 để chống race condition
            $exportStockModel = new ExportStock();
            $newRows          = $exportStockModel->filterNewRows($validRows);
            $skippedCount     = count($validRows) - count($newRows);

            if (!empty($newRows)) {
                $exportStockModel->importFromSheets($newRows);
            }

            $added   = count($newRows);
            $message = $added > 0
                ? sprintf('Đã đồng bộ %d bản ghi mới.', $added)
                : 'Không có bản ghi mới (tất cả đã tồn tại).';

            return new JsonModel([
                'success'      => true,
                'added'        => $added,
                'skipped'      => $skippedCount,
                'message'      => $message,
            ]);

        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            CommonService::loggerException()->error($e->getMessage());
            return new JsonModel([
                'success' => false,
                'message' => 'Đã xảy ra lỗi không xác định.',
            ]);
        }
    }
}
