<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\AdminModule;

use Doctrine\ORM\EntityManager;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Http\Session;
use Nette\Utils\Image;
use Venne\Files\File;
use Venne\Files\PermissionDeniedException;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FilePresenter extends \Nette\Application\UI\Presenter
{

	const DIRECTORY_CACHE = '_cache';

	/** @var string */
	public $size;

	/** @var string */
	public $format;

	/** @var string */
	public $type;

	/** @var string */
	public $url;

	/** @var string */
	private $cacheDir;

	/** @var bool */
	private $cached = false;

	/** @var \Kdyby\Doctrine\EntityRepository */
	private $fileRepository;

	/** @var \Nette\Http\Session */
	private $session;

	public function __construct($cacheDir, EntityManager $entityManager, Session $session)
	{
		$this->cacheDir = $cacheDir;
		$this->fileRepository = $entityManager->getRepository(File::class);
		$this->autoCanonicalize = false;
		$this->session = $session;
	}

	protected function startup()
	{
		parent::startup();

		$this->size = $this->getParameter('size');
		$this->format = $this->getParameter('format');
		$this->type = $this->getParameter('type');
		$this->url = $this->getParameter('url');
	}

	public function actionDefault()
	{
		if (!($file = $this->fileRepository->findOneBy(array('path' => $this->url)))) {
			throw new BadRequestException;
		}

		try {
			$file = $file->getFilePath();

			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime_type = finfo_file($finfo, $file);
			finfo_close($finfo);

			header('Content-type: ' . $mime_type);
			echo file_get_contents($file);
			$this->terminate();
		} catch (PermissionDeniedException $e) {
			throw new ForbiddenRequestException;
		}
	}

	public function actionImage()
	{
		if (substr($this->url, 0, 7) === self::DIRECTORY_CACHE . '/') {
			$this->cached = true;
			$this->url = substr($this->url, 7);
		}

		if (($entity = $this->fileRepository->findOneBy(array('path' => $this->url))) === null) {
			throw new \Nette\Application\BadRequestException(sprintf('File \'%s\' does not exist.', $this->url));
		}

		$image = Image::fromFile($entity->getFilePath());

		$this->session->close();

		// resize
		if ($this->size && $this->size !== 'default') {
			if (strpos($this->size, 'x') !== false) {
				$format = explode('x', $this->size);
				$width = $format[0] !== '?' ? $format[0] : null;
				$height = $format[1] !== '?' ? $format[1] : null;
				$image->resize($width, $height, $this->format !== 'default' ? $this->format : Image::FIT);
			}
		}

		if (!$this->type) {
			$this->type = substr($entity->getName(), strrpos($entity->getName(), '.'));
		}

		$type = $this->type === 'jpg' ? Image::JPEG : $this->type === 'gif' ? Image::GIF : Image::PNG;

		$file = sprintf(
			'%s/%s/%s/%s/%s/%s',
			$this->cacheDir,
			self::DIRECTORY_CACHE,
			$this->size,
			$this->format,
			$this->type,
			$entity->getPath()
		);
		$dir = dirname($file);
		umask(0000);
		@mkdir($dir, 0777, true);
		$image->save($file, 90, $type);
		$image->send($type, 90);
	}

}
