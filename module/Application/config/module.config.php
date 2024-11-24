<?php

declare(strict_types=1);

namespace Application;

use Application\Service\CsvService;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'router' => [
        'routes' => [
            'product' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/product[/:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\ProductController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'warehouse' => [
                'type'    => Segment::class,
                'options' => [
                    'route'    => '/warehouse[/:action][/:id]',
                    'defaults' => [
                        'controller' => Controller\WarehouseController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\IndexController::class     => InvokableFactory::class,
            Controller\ProductController::class   => InvokableFactory::class,
            Controller\WarehouseController::class => InvokableFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            CsvService::class => InvokableFactory::class,
        ]
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => [
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'application/index/index' => __DIR__ . '/../view/application/index/index.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ],
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
        'strategies' => ['ViewJsonStrategy']
    ],
];
