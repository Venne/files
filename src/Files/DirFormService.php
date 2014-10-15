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

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class DirFormService extends \Venne\System\DoctrineFormService
{

	/** @var \Venne\Files\FileFormFactory */
	private $formFactory;

	public function __construct(
		DirFormFactory $formFactory,
		EntityManager $entityManager,
		EntityFormMapper $entityFormMapper
	) {
		parent::__construct($formFactory, $entityManager, $entityFormMapper);
		$this->formFactory = $formFactory;
	}

	/**
	 * @param mixed|null $primaryKey
	 * @param integer|null $parentId
	 * @return \Venne\Forms\FormFactory
	 */
	public function getFormFactory($primaryKey = null, $parentId = null)
	{
		return $this->createFormFactory(
			$this->formFactory,
			$this->getEntity($primaryKey, $parentId)
		);
	}

	/**
	 * @param integer|null $primaryKey
	 * @param integer|null $parentId
	 * @return \Venne\Files\Dir
	 */
	protected function getEntity($primaryKey, $parentId = null)
	{
		$entity = parent::getEntity($primaryKey);

		if ($parentId !== null) {
			$entity->setParent($this->getRepository()->find($parentId));
		}

		return $entity;
	}

	/**
	 * @return string
	 */
	protected function getEntityClassName()
	{
		return Dir::class;
	}

	protected function error(Form $form, \Exception $e)
	{
		if ($e instanceof \Kdyby\Doctrine\DuplicateEntryException) {
			$form['name']->addError($form->getTranslator()->translate('Name must be unique.'));

			return;
		}

		parent::error($form, $e);
	}

}
