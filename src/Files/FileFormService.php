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

use Doctrine\ORM\EntityManager;
use Kdyby\Doctrine\Entities\BaseEntity;
use Kdyby\DoctrineForms\EntityFormMapper;
use Nette\Application\UI\Form;
use Venne\Security\Role;
use Venne\System\DoctrineFormService;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class FileFormService extends \Venne\System\DoctrineFormService
{

	const TYPE_DEFAULT = 'default';

	const TYPE_EDIT = 'edit';

	/** @var \Venne\Files\FileFormFactory */
	private $formFactory;

	/** @var \Venne\Files\FileEditFormFactory */
	private $fileEditFormFactory;

	/** @var \Kdyby\Doctrine\EntityRepository */
	private $dirRepository;

	public function __construct(
		FileFormFactory $formFactory,
		FileEditFormFactory $fileEditFormFactory,
		EntityManager $entityManager,
		EntityFormMapper $entityFormMapper
	) {
		parent::__construct($formFactory, $entityManager, $entityFormMapper);
		$this->formFactory = $formFactory;
		$this->fileEditFormFactory = $fileEditFormFactory;
		$this->dirRepository = $entityManager->getRepository(Dir::class);
	}

	/**
	 * @param mixed|null $primaryKey
	 * @param integer|null $parentId
	 * @param string $type
	 * @return \Venne\Forms\FormFactory
	 */
	public function getFormFactory($primaryKey = null, $parentId = null, $type = self::TYPE_DEFAULT)
	{
		return $this->createFormFactory(
			$type === self::TYPE_EDIT ? $this->fileEditFormFactory : $this->formFactory,
			$this->getEntity($primaryKey, $parentId)
		);
	}

	/**
	 * @param integer|null $primaryKey
	 * @param integer|null $parentId
	 * @return \Venne\Files\File
	 */
	protected function getEntity($primaryKey, $parentId = null)
	{
		$entity = parent::getEntity($primaryKey);

		if ($parentId !== null) {
			$entity->setParent($this->dirRepository->find($parentId));
		}

		return $entity;
	}

	/**
	 * @return string
	 */
	protected function getEntityClassName()
	{
		return File::class;
	}

	protected function error(Form $form, \Exception $e)
	{
		if ($e instanceof \Kdyby\Doctrine\DuplicateEntryException) {
			$form['name']->addError($form->getTranslator()->translate('Name must be unique.'));

			return;
		}

		if ($e instanceof UploadFileException) {
			$form['name']->addError($form->getTranslator()->translate('Failed to upload file.'));

			return;
		}

		if ($e instanceof RenameFileException) {
			$form['name']->addError($form->getTranslator()->translate('Failed to rename file.'));

			return;
		}

		parent::error($form, $e);
	}

}
