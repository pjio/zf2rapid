<?php
/**
 * ZF2rapid - Zend Framework 2 Rapid Development Tool
 *
 * @link      https://github.com/ZFrapid/zf2rapid
 * @copyright Copyright (c) 2014 - 2015 Ralf Eggert
 * @license   http://opensource.org/licenses/MIT The MIT License (MIT)
 */
namespace ZF2rapid\Generator;

use Zend\Code\Generator\AbstractGenerator;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Db\Metadata\Object\ColumnObject;
use Zend\Db\Metadata\Object\ConstraintObject;
use Zend\Db\Metadata\Object\TableObject;
use Zend\Filter\StaticFilter;

/**
 * Class EntityClassGenerator
 *
 * @package ZF2rapid\Generator
 */
class EntityClassGenerator extends ClassGenerator
    implements ClassGeneratorInterface
{
    /**
     * @var array
     */
    protected $config = array();

    /**
     * @var
     */
    protected $tableObject;

    /**
     * @param array       $config
     * @param TableObject $tableObject
     */
    public function __construct(
        array $config = array(), TableObject $tableObject
    ) {
        // set config data
        $this->config      = $config;
        $this->tableObject = $tableObject;

        // call parent constructor
        parent::__construct();
    }

    /**
     * Build the class
     *
     * @param string $className
     * @param string $moduleName
     */
    public function build($className, $moduleName)
    {
        // set name and namespace
        $this->setName($className);
        $this->setNamespaceName(
            $moduleName . '\\' . $this->config['namespaceEntity']
        );

        // add used namespaces and extended classes
        $this->addUse('ZF2rapidDomain\Entity\AbstractEntity');
        $this->setExtendedClass('AbstractEntity');
        $this->addClassDocBlock($className, $moduleName);

        // get table columns
        $tableColumns = $this->fetchTableColumns();

        // get primary columns
        $primaryColumns = $this->fetchPrimaryColumns();

        // add identifier method
        $this->addMethodFromGenerator(
            $this->generateIdentifierMethod($primaryColumns, $tableColumns)
        );

        // loop through table columns
        foreach ($tableColumns as $columnName => $columnType) {
            $this->addPropertyFromGenerator(
                $this->generateProperty($columnName, $columnType)
            );
            $this->addMethodFromGenerator(
                $this->generateSetMethod($columnName, $columnType)
            );
            $this->addMethodFromGenerator(
                $this->generateGetMethod($columnName, $columnType)
            );
        }
    }

    /**
     * Add a class doc block
     *
     * @param string $className
     * @param string $moduleName
     */
    protected function addClassDocBlock($className, $moduleName)
    {
        // check for api docs
        if ($this->config['flagAddDocBlocks']) {
            $this->setDocBlock(
                new DocBlockGenerator(
                    $this->getName(),
                    'Provides the ' . $className . ' entity for the '
                    . $moduleName . ' Module',
                    array(
                        new GenericTag('package', $this->getNamespaceName()),
                    )
                )
            );
        }
    }

    /**
     * @return array
     */
    protected function fetchTableColumns()
    {
        $foreignKeys = array();

        /** @var ConstraintObject $tableConstraint */
        foreach ($this->tableObject->getConstraints() as $tableConstraint) {
            if (!$tableConstraint->isForeignKey()) {
                continue;
            }

            $foreignKeys[$tableConstraint->getColumns()[0]]
                = $tableConstraint->getReferencedTableName();
        }

        $columns = array();

        /** @var $tableColumn ColumnObject */
        foreach ($this->tableObject->getColumns() as $tableColumn) {
            if (isset($foreignKeys[$tableColumn->getName()])) {
                $type = ucfirst($foreignKeys[$tableColumn->getName()])
                    . 'Entity';
            } else {
                switch ($tableColumn->getDataType()) {
                    case 'varchar':
                    case 'char':
                    case 'text':
                    case 'enum':
                    case 'set':
                    case 'datetime':
                    case 'timestamp':
                        $type = 'string';
                        break;

                    default:
                        $type = 'integer';
                }
            }

            $name = lcfirst(
                StaticFilter::execute(
                    $tableColumn->getName(), 'Word\UnderscoreToCamelCase'
                )
            );

            $columns[$name] = $type;
        }

        return $columns;
    }

    /**
     * @return array
     */
    protected function fetchPrimaryColumns()
    {
        $primaryColumns = array();

        /** @var $tableConstraint ConstraintObject */
        foreach ($this->tableObject->getConstraints() as $tableConstraint) {
            if (!$tableConstraint->isPrimaryKey()) {
                continue;
            }

            $primaryColumns = array_merge(
                $primaryColumns, $tableConstraint->getColumns()
            );
        }

        return $primaryColumns;
    }

    /**
     * @param array $primaryColumns
     * @param array $tableColumns
     *
     * @return MethodGenerator
     */
    protected function generateIdentifierMethod(
        array $primaryColumns, array $tableColumns
    ) {
        if (count($primaryColumns) == 1) {
            $columnType = $tableColumns[$primaryColumns[0]];

            $getMethodName = 'get' . ucfirst($primaryColumns[0]);

            $body = array(
                'return $this->' . $getMethodName . '();',
            );
        } else {
            $columnType = 'array';

            $methodCalls = array();

            foreach ($primaryColumns as $primaryColumn) {
                $getMethodName = 'get' . ucfirst($primaryColumn);

                $methodCalls[] = '$this->' . $getMethodName . '()';
            }

            $body = array(
                'return array(',
                '    ' . implode(', ', $methodCalls),
                ');',
            );
        }

        $body = implode(AbstractGenerator::LINE_FEED, $body);

        $getMethod = new MethodGenerator('getIdentifier');
        $getMethod->addFlag(MethodGenerator::FLAG_PUBLIC);
        $getMethod->setDocBlock(
            new DocBlockGenerator(
                'Get the primary identifier',
                null,
                array(
                    array(
                        'name'        => 'return',
                        'description' => $columnType,
                    )
                )
            )
        );
        $getMethod->setBody($body);

        return $getMethod;
    }

    /**
     * @param $columnName
     * @param $columnType
     *
     * @return PropertyGenerator
     */
    protected function generateProperty($columnName, $columnType)
    {
        $property = new PropertyGenerator($columnName);
        $property->addFlag(PropertyGenerator::FLAG_PROTECTED);
        $property->setDocBlock(
            new DocBlockGenerator(
                $columnName . ' property',
                null,
                array(
                    array(
                        'name'        => 'var',
                        'description' => $columnType,
                    )
                )
            )
        );

        return $property;
    }

    /**
     * @param $columnName
     * @param $columnType
     *
     * @return MethodGenerator
     */
    protected function generateSetMethod($columnName, $columnType)
    {
        if (in_array($columnType, array('string', 'integer'))) {
            $body = '$this->' . $columnName . ' = (' . $columnType . ') $'
                . $columnName . ';';

            $parameter = new ParameterGenerator($columnName);
        } else {
            $body = '$this->' . $columnName . ' = $' . $columnName . ';';

            $parameter = new ParameterGenerator($columnName, $columnType);
        }

        $setMethodName = 'set' . ucfirst($columnName);

        $setMethod = new MethodGenerator($setMethodName);
        $setMethod->addFlag(MethodGenerator::FLAG_PROTECTED);
        $setMethod->setParameter($parameter);
        $setMethod->setDocBlock(
            new DocBlockGenerator(
                'Set ' . $columnName,
                null,
                array(
                    array(
                        'name'        => 'param',
                        'description' => $columnType . ' $' . $columnName,
                    )
                )
            )
        );

        $setMethod->setBody($body);

        return $setMethod;
    }

    /**
     * @param $columnName
     * @param $columnType
     *
     * @return MethodGenerator
     */
    protected function generateGetMethod($columnName, $columnType)
    {
        $getMethodName = 'get' . ucfirst($columnName);

        $getMethod = new MethodGenerator($getMethodName);
        $getMethod->addFlag(MethodGenerator::FLAG_PUBLIC);
        $getMethod->setDocBlock(
            new DocBlockGenerator(
                'Get ' . $columnName,
                null,
                array(
                    array(
                        'name'        => 'return',
                        'description' => $columnType,
                    )
                )
            )
        );
        $getMethod->setBody('return $this->' . $columnName . ';');

        return $getMethod;
    }
}