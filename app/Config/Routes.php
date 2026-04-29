<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Sensor API routes
$routes->post('/api/sensor', 'Api\SensorController::store');
$routes->get('/api/sensor/latest', 'Api\SensorController::latest');
$routes->get('/api/sensor/latest/(:segment)', 'Api\SensorController::latest/$1');
