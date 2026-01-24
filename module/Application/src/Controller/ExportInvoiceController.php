<?php

declare(strict_types=1);

namespace Application\Controller;

use Application\Model\ExportInvoice;
use Application\Model\ExportStock;
use Application\Model\PdfGenerator;
use Application\Model\Product;
use Application\Service\CommonService;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Laminas\View\Model\ViewModel;

class ExportInvoiceController extends AbstractActionController
{
    public function indexAction() {
        return new ViewModel();
    }

    public function addAction()
    {
        $date = $this->params()->fromRoute('date', '');
        $productModel = new Product();
        $productList = $productModel->getData();

        $exportStockModel = new ExportStock();
        $exportStockList = $exportStockModel->getDataByKeyTypeDate('date', $date);
        $exportProductList = $exportStockModel->mergeExportStockByItem($exportStockList, $productList);


        return new ViewModel(['date' => $date, 'exportProductList' => $exportProductList, 'productList' => $productList]);
    }

    public function doAddAction() {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $exportInvoiceModel = new ExportInvoice();
        $exportInvoiceModel->doAdd($postData);

        $this->flashMessenger()->addSuccessMessage('Thêm thành công');
        return $this->redirect()->toRoute('exportInvoice');
    }

    public function editAction()
    {
        $id = $this->params()->fromRoute('id', '');

        $exportInvoiceModel = new ExportInvoice();
        $data = $exportInvoiceModel->getDataById($id);
        $date = $data['date'] ?? '';
        $content = $data['content'] ?? '';
        $content = json_decode($content, true);

        $productModel = new Product();
        $productList = $productModel->getData();

        return new ViewModel(['id' => $id, 'date' => $date, 'content' => $content, 'productList' => $productList]);
    }

    public function doEditAction() {
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();

        $exportInvoiceModel = new ExportInvoice();
        $exportInvoiceModel->doEdit($postData);

        $this->flashMessenger()->addSuccessMessage('Sửa thành công');
        return $this->redirect()->toRoute('exportInvoice');
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
            $exportInvoiceModel = new ExportInvoice();
            $exportInvoiceModel->deleteRow($id);

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

    public function pdfAction() {
        $id = $this->params()->fromRoute('id', '');
        $exportInvoiceModel = new ExportInvoice();
        $pdfData = $exportInvoiceModel->generatePdf($id);

        $response = $this->getResponse();
        $response->getHeaders()->addHeaders([
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="so-doanh-thu.pdf"',
        ]);

        return $response->setContent($pdfData);
    }

    public function dataTableServerSideAction()
    {
        try {
            $request = $this->getRequest();
            $postData = $request->getPost();

            $exportInvoiceModel = new ExportInvoice();
            $data = $exportInvoiceModel->getDataToView();

            $response = CommonService::dataTableServerSideProcessing($postData, $data);
            return new JsonModel($response);

        } catch (\RuntimeException $e) {
            return new JsonModel([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
