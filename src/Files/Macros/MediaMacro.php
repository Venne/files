<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace Venne\Files\Macros;

use Doctrine\ORM\EntityManager;
use Latte\CompileException;
use Latte\Compiler;
use Latte\MacroNode;
use Nette\Utils\Strings;
use Venne\Files\File;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class MediaMacro extends \Latte\Macros\MacroSet
{

	/** @var \Kdyby\Doctrine\EntityRepository */
	private static $fileRepository;

	/** @var string[] */
	private static $imageExtensions = array('jpeg', 'png', 'gif');

	/**
	 * @param \Latte\Compiler $compiler
	 * @return \Venne\Files\Macros\MediaMacro
	 */
	public static function install(Compiler $compiler)
	{
		$me = new static($compiler);

		// file
		$me->addMacro('file', array($me, 'macroFile'));
		$me->addMacro('fhref', null, null, function (MacroNode $node, $writer) use ($me) {
			return ' ?> href="<?php ' . $me->macroFile($node, $writer) . ' ?>"<?php ';
		});

		// image
		$me->addMacro('img', array($me, 'macroImage'));
		$me->addMacro('image', array($me, 'macroImage'));
		$me->addMacro('ihref', null, null, function (MacroNode $node, $writer) use ($me) {
			return ' ?> href="<?php ' . $me->macroImage($node, $writer) . ' ?>"<?php ';
		});
		$me->addMacro('src', null, null, function (MacroNode $node, $writer) use ($me) {
			return ' ?> src="<?php ' . $me->macroImage($node, $writer) . ' ?>"<?php ';
		});

		return $me;
	}

	/**
	 * @param \Latte\MacroNode $node
	 * @param string $writer
	 * @return string
	 */
	public static function macroFile(MacroNode $node, $writer)
	{
		return $writer->write('echo $basePath . \Venne\Files\Macros\MediaMacro::proccessFile(%node.word)');
	}

	/**
	 * @param \Latte\MacroNode $node
	 * @param string $writer
	 * @return string
	 */
	public static function macroImage(MacroNode $node, $writer)
	{
		return $writer->write('echo $basePath . \Venne\Files\Macros\MediaMacro::proccessImage(%node.word, %node.array)');
	}

	/**
	 * @param string $path
	 * @return string
	 */
	public static function proccessFile($path)
	{
		return '/public/media/' . $path;
	}

	/**
	 * @param string $path
	 * @param string[] $args
	 * @return string
	 */
	public static function proccessImage($path, $args = array())
	{
		$size = isset($args['size']) ? $args['size'] : 'default';
		$format = isset($args['format']) ? $args['format'] : 'default';
		$type = isset($args['type']) ? $args['type'] : 'default';

		$ext = new \SplFileInfo($path);
		$ext = str_replace('jpg', 'jpeg', Strings::lower($ext->getExtension()));

		if (array_search($ext, self::$imageExtensions) === false || ($type !== 'default' && array_search($type, self::$imageExtensions) === false)) {
			throw new CompileException(sprintf('Bad extension of file \'%s\'. You can use only: %s', $path, implode(', ', self::$imageExtensions)));
		}

		if ($format == 'default' && ($type == 'default' || $type == $ext) && $size == 'default') {
			return '/public/media/' . $path;
		}

		return sprintf('/public/media/_cache/%s/%s/%s/%s', $size, $format, $type, $path);
	}

	public static function setEntityManager(EntityManager $entityManager)
	{
		self::$fileRepository = $entityManager->getRepository(File::class);
	}

}
