<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\ExportStockRepository;
use Application\Repository\ProductRepository;
use Application\Service\CommonService;
use Application\Service\GoogleSheetsService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ExportStockController extends AbstractActionController
{
    private ExportStockRepository $exportRepo;
    private ProductRepository     $productRepo;

    public function __construct(ExportStockRepository $exportRepo, ProductRepository $productRepo)
    {
        $this->exportRepo  = $exportRepo;
        $this->productRepo = $productRepo;
    }

    public function indexAction()
    {
        return new ViewModel();
    }

    public function addAction()
    {
        $productList = $this->productRepo->getData();
        return new ViewModel(['productList' => $productList]);
    }

    public function doAddAction()
    {
        try {
            $postData        = $this->getRequest()->getPost()->toArray();
            $productNameList = $this->productRepo->getProductNameList();
            $this->exportRepo->doAdd($postData, $productNameList);
            return new JsonModel(['success' => true, 'message' => 'Thêm thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function editAction()
    {
        $date            = $this->params()->fromRoute('date', '');
        $exportStockList = $this->exportRepo->getDataByDate($date);
        $productList     = $this->productRepo->getData();
        return new ViewModel(['date' => $date, 'exportStockList' => $exportStockList, 'productList' => $productList]);
    }

    public function doEditAction()
    {
        try {
            $postData        = $this->getRequest()->getPost()->toArray();
            $date            = $postData['date'][0] ?? ($postData['date'] ?? '');
            $productNameList = $this->productRepo->getProductNameList();
            $this->exportRepo->doEdit($postData, $productNameList);

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
            $data = json_decode($this->getRequest()->getContent(), true);
            if (!isset($data['id'])) {
                return new JsonModel(['success' => false, 'message' => 'ID không được cung cấp.']);
            }
            $this->exportRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            $data     = $this->exportRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function syncPreviewAction()
    {
        try {
            $input = json_decode($this->getRequest()->getContent(), true) ?? [];
            $date  = trim((string) ($input['date'] ?? ''));

            $sheetsService = new GoogleSheetsService();
            $allRows       = $sheetsService->fetchAll();

            $rows = $date !== ''
                ? array_values(array_filter($allRows, fn($r) => ($r['date'] ?? '') === $date))
                : $allRows;

            $categorized  = $this->exportRepo->categorizeSyncRows($rows);
            $newRows      = $categorized['allSync'];
            $newCount     = count($categorized['new']);
            $updatedCount = count($categorized['updated']);
            $skippedCount = count($categorized['unchanged']);

            return new JsonModel([
                'success'      => true,
                'newRows'      => $newRows,
                'newCount'     => $newCount,
                'updatedCount' => $updatedCount,
                'skippedCount' => $skippedCount,
            ]);
        } catch (\RuntimeException $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            CommonService::loggerException()->error($e->getMessage());
            return new JsonModel(['success' => false, 'message' => 'Đã xảy ra lỗi không xác định.']);
        }
    }

    public function doSyncAction()
    {
        try {
            $input = json_decode($this->getRequest()->getContent(), true) ?? [];
            $rows  = $input['rows'] ?? [];

            if (empty($rows) || !is_array($rows)) {
                return new JsonModel(['success' => false, 'message' => 'Không có dữ liệu rows để đồng bộ.']);
            }

            $requiredFields = ['id', 'date', 'productId', 'productName', 'quantity'];
            $validRows = array_values(array_filter($rows, function ($row) use ($requiredFields): bool {
                if (!is_array($row)) return false;
                foreach ($requiredFields as $f) {
                    if (!isset($row[$f]) || $row[$f] === '') return false;
                }
                return true;
            }));

            if (empty($validRows)) {
                return new JsonModel(['success' => false, 'message' => 'Không có bản ghi hợp lệ để đồng bộ.']);
            }

            $categorized  = $this->exportRepo->categorizeSyncRows($validRows);
            $newCount     = count($categorized['new']);
            $updatedCount = count($categorized['updated']);

            $this->exportRepo->importFromSheets($validRows);

            $parts = [];
            if ($newCount > 0) $parts[] = "$newCount bản ghi mới";
            if ($updatedCount > 0) $parts[] = "$updatedCount bản ghi cập nhật";
            $message = !empty($parts) ? 'Đã đồng bộ thành công: ' . implode(', ', $parts) . '.' : 'Đã đồng bộ thành công!';

            return new JsonModel([
                'success'      => true,
                'newCount'     => $newCount,
                'updatedCount' => $updatedCount,
                'message'      => $message,
            ]);
        } catch (\RuntimeException $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            CommonService::loggerException()->error($e->getMessage());
            return new JsonModel(['success' => false, 'message' => 'Đã xảy ra lỗi không xác định.']);
        }
    }
}
