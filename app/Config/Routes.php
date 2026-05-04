<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers\Api'], function($routes) {

    // Devices
    $routes->get('devices',                      'DeviceController::getAllDevices');
    $routes->get('devices/(:segment)',           'DeviceController::getDevice/$1');
    $routes->get('devices/(:segment)/status',    'DeviceController::getDeviceStatus/$1');

    // Sensors
    $routes->get('sensors/latest',               'SensorController::getLatestAll');
    $routes->get('sensors/(:segment)/latest',    'SensorController::getLatest/$1');
    $routes->get('sensors/(:segment)/history',   'SensorController::getHistory/$1');
    $routes->get('sensors/(:segment)/stats',     'SensorController::getStats/$1');

    // Bills
    $routes->get('bills/all',                    'BillController::getAllBills');
    $routes->get('bills/(:segment)/predict',     'BillController::predictBill/$1');
    $routes->get('bills/(:segment)/history',     'BillController::getBillHistory/$1');

    // AI Tips
    $routes->get('tips/all',                     'TipsController::getAllLatestTips');
    $routes->get('tips/(:segment)',              'TipsController::getTips/$1');
    $routes->get('tips/(:segment)/history',      'TipsController::getTipsHistory/$1');

    // Firebase Sync (internal — protect with IP whitelist or secret key)
    $routes->get('sync/sensors',                 '\App\Controllers\FirebaseSync::syncSensors');
    $routes->get('sync/devices',                 '\App\Controllers\FirebaseSync::syncDevices');
});
