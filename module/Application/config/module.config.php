<?php

declare(strict_types=1);

namespace Application;

use Application\Library\LeagueCsv;
use Application\Service\CommonService;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;

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
    'router'          => [
        'routes' => [
            'default'     => createSegmentRoute(Controller\ProductController::class, '/'),
            'product'     => createSegmentRoute(Controller\ProductController::class, '/product', [
                'edit'   => createChildRoute('edit', ['id']),
                'delete' => createChildRoute('delete', ['id'])
            ]),
            'exportStock' => createSegmentRoute(Controller\ExportStockController::class, '/export-stock', [
                'edit'   => createChildRoute('edit', ['date'], ['date' => '\d{2}-\d{2}-\d{4}']),
                'delete' => createChildRoute('delete', ['id'])
            ]),
            'importStock' => createSegmentRoute(Controller\ImportStockController::class, '/import-stock', [
                'edit'   => createChildRoute('edit', ['date'], ['date' => '\d{2}-\d{2}-\d{4}']),
                'delete' => createChildRoute('delete', ['id'])
            ]),
            'vetCare'     => createSegmentRoute(Controller\VetCareController::class, '/vet-care', [
                'edit'   => createChildRoute('edit', ['id']),
                'delete' => createChildRoute('delete', ['id'])
            ]),
            'expenses'    => createSegmentRoute(Controller\ExpensesController::class, '/expenses', [
                'edit'   => createChildRoute('edit', ['date'], ['date' => '\d{2}-\d{2}-\d{4}']),
                'delete' => createChildRoute('delete', ['id'])
            ]),
            'report'      => createSegmentRoute(Controller\ReportController::class, '/report', [
                'edit'   => createChildRoute('edit', ['id']),
                'delete' => createChildRoute('delete', ['id'])
            ]),
        ],
    ],
    'controllers'     => [
        'factories' => [
            Controller\IndexController::class       => InvokableFactory::class,
            Controller\ProductController::class     => InvokableFactory::class,
            Controller\ExportStockController::class => InvokableFactory::class,
            Controller\ImportStockController::class => InvokableFactory::class,
            Controller\VetCareController::class     => InvokableFactory::class,
            Controller\ExpensesController::class    => InvokableFactory::class,
            Controller\ReportController::class      => InvokableFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            LeagueCsv::class => InvokableFactory::class,
            CommonService::class => InvokableFactory::class
        ]
    ],
    'view_manager'    => [
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
        'template_path_stack'      => [
            __DIR__ . '/../view',
        ],
        'strategies'               => ['ViewJsonStrategy']
    ],
];
