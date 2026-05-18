<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', ['namespace' => 'App\Controllers\Api'], function ($routes) {

    // Devices
    $routes->get('devices',                      'DeviceController::getAllDevices');
    $routes->get('devices/(:segment)',           'DeviceController::getDevice/$1');
    $routes->get('devices/(:segment)/status',    'DeviceController::getDeviceStatus/$1');
    $routes->get('devices/(:segment)/export',    'DeviceController::exportData/$1');

    // Sensors
    $routes->get('sensors/latest',               'SensorController::getLatestAll');
    $routes->get('sensors/(:segment)/latest',    'SensorController::getLatest/$1');
    $routes->get('sensors/(:segment)/history',   'SensorController::getHistory/$1');
    $routes->get('sensors/(:segment)/stats',     'SensorController::getStats/$1');
    // Test insert (debug only)
    $routes->post('sensors/test-insert',         'SensorController::insertTestReading');

    // Bills
    $routes->get('bills/all',                    'BillController::getAllBills');
    $routes->post('bills/(:segment)/predict',    'BillController::predictBill/$1');
    $routes->get('bills/(:segment)/history',     'BillController::getBillHistory/$1');

    // AI Tips
    $routes->get('tips/all',                     'TipsController::getAllLatestTips');
    $routes->post('tips/(:segment)/generate',    'TipsController::generateTips/$1');
    $routes->get('tips/(:segment)/history',      'TipsController::getTipsHistory/$1');

    // Firebase Sync (internal — protect with IP whitelist or secret key)
    $routes->get('sync/sensors',                 '\App\Controllers\FirebaseSync::syncSensors');
    $routes->get('sync/devices',                 '\App\Controllers\FirebaseSync::syncDevices');
});
