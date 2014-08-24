<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files;

use Nette\Http\Session;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class AjaxFileUploaderControl extends \Venne\System\UI\Control
{

	/** @var callable */
	public $onFileUpload;

	/** @var callable */
	public $onAfterFileUpload;

	/** @var callable */
	public $onError;

	/** @var callable */
	public $onSuccess;

	/** @var string */
	protected $ajaxDir;

	/** @var string */
	protected $ajaxPath;

	/** @var string[] */
	private $errors = array();

	/** @var \Nette\Http\SessionSection */
	private $sessionSection;

	/**
	 * @param \Nette\ComponentModel\IContainer $ajaxDir
	 * @param string $ajaxPath
	 * @param \Nette\Http\Session $session
	 */
	public function __construct($ajaxDir, $ajaxPath, Session $session)
	{
		parent::__construct();

		$this->ajaxDir = $ajaxDir;
		$this->ajaxPath = $ajaxPath;
		$this->sessionSection = $session->getSection('ajaxUploader-' . $this->getName());
	}

	/**
	 * @return string
	 */
	public function getAjaxDir()
	{
		return $this->ajaxDir;
	}

	/**
	 * @return string
	 */
	public function getAjaxPath()
	{
		return $this->ajaxPath;
	}

	/**
	 * @param string $class
	 * @param string $message
	 * @param int $code
	 */
	protected function addError($class, $message, $code)
	{
		if (!isset($this->sessionSection->errors)) {
			$this->sessionSection->errors = array();
		}

		$this->sessionSection->errors[] = array(
			'class' => $class,
			'message' => $message,
			'code' => $code,
		);
	}

	protected function cleanErrors()
	{
		$this->sessionSection->errors = array();
	}

	/**
	 * @return string[]
	 */
	public function getErrors()
	{
		if (!isset($this->sessionSection->errors)) {
			$this->sessionSection->errors = array();
		}

		return $this->sessionSection->errors;
	}

	public function handleUpload()
	{
		$this->cleanErrors();

		if (!file_exists($this->ajaxDir)) {
			mkdir($this->ajaxDir, 0777, true);
		}

		if (!class_exists('\UploadHandler')) {
			include_once __DIR__ . '/../../../../blueimp/jquery-file-upload/server/php/UploadHandler.php';
		}

		ob_start();
		new \UploadHandler(array(
			'upload_dir' => $this->ajaxDir . '/',
			'upload_url' => $this->ajaxPath . '/',
			'script_url' => $this->ajaxPath . '/',

		));
		$data = json_decode(ob_get_clean(), true);

		foreach ($data['files'] as $file) {
			//try {
			$this->onFileUpload($this, $file['name']);
			//} catch (\Exception $e) {
			//	$this->addError(get_class($e), $e->getMessage(), $e->getCode());
			//}

			try {
				$this->onAfterFileUpload($this, $file['name']);
			} catch (\Exception $e) {
				$this->addError(get_class($e), $e->getMessage(), $e->getCode());
			}
		}

		$this->presenter->terminate();
	}

	public function handleSuccess()
	{
		if (count($this->getErrors())) {
			$this->onError($this);
		} else {
			$this->onSuccess($this);
		}
	}

}
