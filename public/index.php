<?php
error_reporting(E_ALL);

try {

	$loader = new \Phalcon\Loader();

	$loader->registerDirs(
			array(
				'../app/controller/'
			)
	);

	$loader->register();

	$di = new \Phalcon\DI\FactoryDefault();

	$di->set('url', function() use ($config) {
		$url = new \Phalcon\Mvc\Url();
		$url->setBaseUri('/');
		return $url;
	});

	$di->set('view', function() use ($config) {
		$view = new \Phalcon\Mvc\View();
		$view->disable();
		return $view;
	});

	$di->set('router', function () {
		$router = new \Phalcon\Mvc\Router();
		return $router;
	});

	$app = new \Phalcon\Mvc\Application();

	$app->setDI($di);

	echo $app->handle()->getContent();
} catch (Exception $e) {
}
