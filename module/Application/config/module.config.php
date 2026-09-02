<?php

declare(strict_types=1);

namespace Application;

use Application\Controller\ExportInvoiceController;
use Application\Controller\ExportStockController;
use Application\Controller\ImportStockController;
use Application\Controller\MedicalRecordController;
use Application\Controller\OverviewController;
use Application\Controller\OwnerPetController;
use Application\Controller\ProductController;
use Application\Controller\ReportController;
use Application\Controller\SettingsController;
use Application\Controller\StocktakingController;
use Application\Controller\VetCareController;
use Application\Controller\ExpensesController;
use Application\Database\Database;
use Application\Database\DatabaseFactory;
use Application\Library\LeagueCsv;
use Application\Repository\ExportInvoiceRepository;
use Application\Repository\ExportStockRepository;
use Application\Repository\ExpensesRepository;
use Application\Repository\ImportStockRepository;
use Application\Repository\MedicalRecordRepository;
use Application\Repository\OwnerPetRepository;
use Application\Repository\ProductRepository;
use Application\Repository\RepackageHistoryRepository;
use Application\Repository\ReportRepository;
use Application\Repository\StocktakingRepository;
use Application\Repository\VetCareRepository;
use Application\Service\BackupService;
use Application\Service\CommonService;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;
use Psr\Container\ContainerInterface;
use Laminas\Session\Container;
use Laminas\Session\Storage\SessionArrayStorage;

function createSegmentRoute($controller, $baseRoute, $childRoutes = [])
{
    $childRoutesDefault = [
        'default' => [
            'type'    => Segment::class,
            'options' => [
                'route'    => '[/:action]',
                'defaults' => [
                    'action' => 'action',
                ],
            ],
        ]
    ];

    return [
        'type'          => Segment::class,
        'options'       => [
            'route'    => $baseRoute,
            'defaults' => [
                'controller' => $controller,
                'action'     => 'index',
            ],
        ],
        'may_terminate' => true,
        'child_routes'  => array_merge($childRoutesDefault, $childRoutes),
    ];
}

function createChildRoute($action, $params = [], $constraints = [])
{
    $paramSegment = array_map(fn($param) => sprintf(':%s', $param), $params);
    $routePath = sprintf('/%s/%s', $action, implode('/', $paramSegment));

    return [
        'type'    => Segment::class,
        'options' => [
            'route'       => $routePath,
            'constraints' => $constraints,
            'defaults'    => [
                'action' => $action,
            ],
        ],
    ];
}

return [
    'router' => [
        'routes' => [
            'default'     => createSegmentRoute(OverviewController::class, '/'),
            'overview'    => createSegmentRoute(OverviewController::class, '/overview', []),
            'product'     => createSegmentRoute(ProductController::class, '/product', [
                'edit'   => createChildRoute('edit',   ['id']),
                'delete' => createChildRoute('delete', ['id']),
            ]),
            'stocktaking' => createSegmentRoute(StocktakingController::class, '/stocktaking', []),
            'exportStock' => createSegmentRoute(ExportStockController::class, '/export-stock', [
                'edit'        => createChildRoute('edit',   ['date'], ['date' => '\d{2}-\d{2}-\d{4}']),
                'delete'      => createChildRoute('delete', ['id']),
                'syncPreview' => [
                    'type'    => Segment::class,
                    'options' => ['route' => '/sync-preview', 'defaults' => ['action' => 'syncPreview']],
                ],
                'doSync' => [
                    'type'    => Segment::class,
                    'options' => ['route' => '/do-sync', 'defaults' => ['action' => 'doSync']],
                ],
            ]),
            'exportInvoice' => createSegmentRoute(ExportInvoiceController::class, '/export-invoice', [
                'add'  => createChildRoute('add',  ['date'], ['date' => '\d{2}-\d{2}-\d{4}']),
                'edit' => createChildRoute('edit', ['id']),
                'pdf'  => createChildRoute('pdf',  ['id']),
            ]),
            'importStock' => createSegmentRoute(ImportStockController::class, '/import-stock', [
                'edit'   => createChildRoute('edit',   ['date'], ['date' => '\d{2}-\d{2}-\d{4}']),
                'delete' => createChildRoute('delete', ['id']),
            ]),
            'vetCare'  => createSegmentRoute(VetCareController::class, '/vet-care', [
                'edit'   => createChildRoute('edit',   ['id']),
                'delete' => createChildRoute('delete', ['id']),
            ]),
            'expenses' => createSegmentRoute(ExpensesController::class, '/expenses', [
                'edit'   => createChildRoute('edit',   ['date'], ['date' => '\d{2}-\d{2}-\d{4}']),
                'delete' => createChildRoute('delete', ['id']),
            ]),
            'report' => createSegmentRoute(ReportController::class, '/report', [
                'edit'        => createChildRoute('edit',   ['id']),
                'delete'      => createChildRoute('delete', ['id']),
                'dataByDate'  => [
                    'type'    => Segment::class,
                    'options' => ['route' => '/data-by-date', 'defaults' => ['action' => 'dataByDate']],
                ],
            ]),
            'pdf'      => createSegmentRoute(Controller\PdfController::class, '/pdf', []),
            'ownerPet' => createSegmentRoute(OwnerPetController::class, '/owner-pet', [
                'edit'   => createChildRoute('edit',   ['id']),
                'delete' => createChildRoute('delete', ['id']),
            ]),
            'medicalRecord' => createSegmentRoute(MedicalRecordController::class, '/medical-record', [
                'add'     => createChildRoute('add',     ['petId']),
                'edit'    => createChildRoute('edit',    ['id']),
                'history' => createChildRoute('history', ['petId']),
            ]),
            'settings' => createSegmentRoute(SettingsController::class, '/settings', [
                'doRestore' => [
                    'type'    => Segment::class,
                    'options' => ['route' => '/do-restore', 'defaults' => ['action' => 'doRestore']],
                ],
            ]),
        ],
    ],

    'controllers' => [
        'factories' => [
            // ── Controllers with Repository injection ──────────────────────
            OverviewController::class => static function (ContainerInterface $c): OverviewController {
                return new OverviewController($c->get(ReportRepository::class));
            },
            ProductController::class => static function (ContainerInterface $c): ProductController {
                return new ProductController(
                    $c->get(ProductRepository::class),
                    $c->get(RepackageHistoryRepository::class)
                );
            },
            StocktakingController::class => static function (ContainerInterface $c): StocktakingController {
                return new StocktakingController(
                    $c->get(ProductRepository::class),
                    $c->get(StocktakingRepository::class)
                );
            },
            ImportStockController::class => static function (ContainerInterface $c): ImportStockController {
                return new ImportStockController(
                    $c->get(ImportStockRepository::class),
                    $c->get(ProductRepository::class)
                );
            },
            ExportStockController::class => static function (ContainerInterface $c): ExportStockController {
                return new ExportStockController(
                    $c->get(ExportStockRepository::class),
                    $c->get(ProductRepository::class)
                );
            },
            VetCareController::class => static function (ContainerInterface $c): VetCareController {
                return new VetCareController($c->get(VetCareRepository::class));
            },
            ExpensesController::class => static function (ContainerInterface $c): ExpensesController {
                return new ExpensesController($c->get(ExpensesRepository::class));
            },
            ReportController::class => static function (ContainerInterface $c): ReportController {
                return new ReportController(
                    $c->get(ReportRepository::class),
                    $c->get(ExportStockRepository::class),
                    $c->get(VetCareRepository::class),
                    $c->get(ExpensesRepository::class),
                    $c->get(BackupService::class)
                );
            },
            ExportInvoiceController::class => static function (ContainerInterface $c): ExportInvoiceController {
                return new ExportInvoiceController(
                    $c->get(ExportInvoiceRepository::class),
                    $c->get(ExportStockRepository::class),
                    $c->get(ProductRepository::class)
                );
            },
            OwnerPetController::class => static function (ContainerInterface $c): OwnerPetController {
                return new OwnerPetController($c->get(OwnerPetRepository::class));
            },
            MedicalRecordController::class => static function (ContainerInterface $c): MedicalRecordController {
                return new MedicalRecordController(
                    $c->get(MedicalRecordRepository::class),
                    $c->get(OwnerPetRepository::class)
                );
            },
            // ── Controllers unchanged (InvokableFactory still works) ───────
            Controller\PdfController::class => InvokableFactory::class,
            SettingsController::class => static function (ContainerInterface $c): SettingsController {
                return new SettingsController($c->get(BackupService::class));
            },
        ],
    ],

    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map'             => [
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => ['ViewJsonStrategy'],
    ],

    // ── Services ────────────────────────────────────────────────────────────
    'service_manager' => [
        'factories' => [
            // Infrastructure
            LeagueCsv::class     => InvokableFactory::class,
            CommonService::class => InvokableFactory::class,
            BackupService::class => InvokableFactory::class,
            Database::class      => DatabaseFactory::class,

            // Repositories — all receive Database via DI
            ProductRepository::class => static function (ContainerInterface $c): ProductRepository {
                return new ProductRepository($c->get(Database::class));
            },
            ImportStockRepository::class => static function (ContainerInterface $c): ImportStockRepository {
                return new ImportStockRepository($c->get(Database::class));
            },
            ExportStockRepository::class => static function (ContainerInterface $c): ExportStockRepository {
                return new ExportStockRepository($c->get(Database::class));
            },
            VetCareRepository::class => static function (ContainerInterface $c): VetCareRepository {
                return new VetCareRepository($c->get(Database::class));
            },
            ExpensesRepository::class => static function (ContainerInterface $c): ExpensesRepository {
                return new ExpensesRepository($c->get(Database::class));
            },
            ReportRepository::class => static function (ContainerInterface $c): ReportRepository {
                return new ReportRepository($c->get(Database::class));
            },
            ExportInvoiceRepository::class => static function (ContainerInterface $c): ExportInvoiceRepository {
                return new ExportInvoiceRepository($c->get(Database::class));
            },
            OwnerPetRepository::class => static function (ContainerInterface $c): OwnerPetRepository {
                return new OwnerPetRepository($c->get(Database::class));
            },
            MedicalRecordRepository::class => static function (ContainerInterface $c): MedicalRecordRepository {
                return new MedicalRecordRepository($c->get(Database::class));
            },
            StocktakingRepository::class => static function (ContainerInterface $c): StocktakingRepository {
                return new StocktakingRepository($c->get(Database::class));
            },
            RepackageHistoryRepository::class => static function (ContainerInterface $c): RepackageHistoryRepository {
                return new RepackageHistoryRepository($c->get(Database::class));
            },
        ],
    ],

    // ── Database ─────────────────────────────────────────────────────────────
    'database' => [
        'path'           => getcwd() . '/data/app.db',
        'migrations_dir' => getcwd() . '/data/migrations',
    ],

    // ── Session ──────────────────────────────────────────────────────────────
    'session_containers' => [Container::class],
    'session_storage'    => ['type' => SessionArrayStorage::class],
    'session_config'     => ['gc_maxlifetime' => 7200],
];
