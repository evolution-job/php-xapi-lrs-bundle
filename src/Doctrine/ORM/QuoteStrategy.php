<?php

/*
 * This file is part of the xAPI package.
 *
 * (c) Christian Flothmann <christian.flothmann@xabbuh.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\Doctrine\ORM;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;
use Doctrine\ORM\Mapping\ClassMetadata;


/**
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
class QuoteStrategy extends DefaultQuoteStrategy
{
    public function getColumnName($fieldName, ClassMetadata $class, AbstractPlatform $platform)
    {
        return isset($class->fieldMappings[$fieldName]['quoted'])
            ? $platform->quoteIdentifier($class->fieldMappings[$fieldName]['columnName'])
            : $this->quote($class->fieldMappings[$fieldName]['columnName']);
    }

    public function getJoinColumnName(array $joinColumn, ClassMetadata $class, AbstractPlatform $platform)
    {
        return isset($joinColumn['quoted'])
            ? $platform->quoteIdentifier($joinColumn['name'])
            : $this->quote($joinColumn['name']);
    }

    public function getReferencedJoinColumnName(array $joinColumn, ClassMetadata $class, AbstractPlatform $platform)
    {
        return isset($joinColumn['quoted'])
            ? $platform->quoteIdentifier($joinColumn['referencedColumnName'])
            : $this->quote($joinColumn['referencedColumnName']);
    }

    private function quote(string $columnName): string
    {
        if (str_starts_with($columnName, '`')) {
            return $columnName;
        }

        return '`' . $columnName . '`';
    }
}