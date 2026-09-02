<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Repository\ExportInvoiceRepository;
use Application\Repository\ExportStockRepository;
use Application\Repository\ProductRepository;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ExportInvoiceController extends AbstractActionController
{
    private ExportInvoiceRepository $invoiceRepo;
    private ExportStockRepository   $exportRepo;
    private ProductRepository       $productRepo;

    public function __construct(
        ExportInvoiceRepository $invoiceRepo,
        ExportStockRepository   $exportRepo,
        ProductRepository       $productRepo
    ) {
        $this->invoiceRepo = $invoiceRepo;
        $this->exportRepo  = $exportRepo;
        $this->productRepo = $productRepo;
    }

    public function indexAction()
    {
        return new ViewModel();
    }

    public function addAction()
    {
        $date            = $this->params()->fromRoute('date', '');
        $productList     = $this->productRepo->getData();
        $exportStockList = $this->exportRepo->getDataByDate($date);
        $exportProductList = $this->exportRepo->mergeExportStockByItem($exportStockList, $productList);
        return new ViewModel(['date' => $date, 'exportProductList' => $exportProductList, 'productList' => $productList]);
    }

    public function doAddAction()
    {
        try {
            $postData = $this->getRequest()->getPost()->toArray();
            $this->invoiceRepo->doAdd($postData);
            return new JsonModel(['success' => true, 'message' => 'Thêm thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function editAction()
    {
        $id          = $this->params()->fromRoute('id', '');
        $data        = $this->invoiceRepo->getDataById($id);
        $date        = $data['date']    ?? '';
        $content     = json_decode($data['content'] ?? '{}', true);
        $productList = $this->productRepo->getData();
        return new ViewModel(['id' => $id, 'date' => $date, 'content' => $content, 'productList' => $productList]);
    }

    public function doEditAction()
    {
        try {
            $postData = $this->getRequest()->getPost()->toArray();
            $this->invoiceRepo->doEdit($postData);
            return new JsonModel(['success' => true, 'message' => 'Cập nhật thành công!']);
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
            $this->invoiceRepo->remove($data['id']);
            return new JsonModel(['success' => true, 'message' => 'Xóa thành công!']);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function pdfAction()
    {
        $id      = $this->params()->fromRoute('id', '');
        $pdfData = $this->invoiceRepo->generatePdf($id);

        $response = $this->getResponse();
        $response->getHeaders()->addHeaders([
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="so-doanh-thu.pdf"',
        ]);
        return $response->setContent($pdfData);
    }

    public function dataTableServerSideAction()
    {
        try {
            $postData = $this->getRequest()->getPost();
            $data     = $this->invoiceRepo->getDataToView();
            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);
        } catch (\Throwable $e) {
            return new JsonModel(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
