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
use Zend\Code\Generator\DocBlock\Tag\ParamTag;
use Zend\Code\Generator\DocBlock\Tag\ReturnTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Db\Metadata\Object\ColumnObject;
use Zend\Db\Metadata\Object\TableObject;
use Zend\Stdlib\Parameters;

/**
 * Class HydratorStrategyGenerator
 *
 * @package ZF2rapid\Generator
 */
class HydratorStrategyGenerator extends ClassGenerator
    implements ClassGeneratorInterface
{
    /**
     * @var array
     */
    protected $config = array();

    /**
     * @var string
     */
    protected $refTableName;

    /**
     * @var array
     */
    protected $tableObjects;

    /**
     * @var string
     */
    protected $entityClass;

    /**
     * @param Parameters $params
     * @param string     $refTableName
     */
    public function __construct(Parameters $params, $refTableName)
    {
        // set config data
        $this->config       = $params->config;
        $this->refTableName = $refTableName;
        $this->tableObjects = $params->currentTableObjects;
        $this->entityClass  = ucfirst($refTableName) . 'Entity';

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
            $moduleName . '\\' . $this->config['namespaceHydrator']
            . '\\Strategy'
        );

        // add used namespaces and extended classes
        $this->addUse('Zend\Stdlib\Hydrator\Strategy\StrategyInterface');
        $this->addUse(
            $moduleName . '\\' . $this->config['namespaceEntity'] . '\\'
            . $this->entityClass
        );
        $this->setImplementedInterfaces(array('StrategyInterface'));

        // add methods
        $this->addExtractMethod();
        $this->addHydrateMethod();
        $this->addClassDocBlock($className, $moduleName);
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
                    'Provides the ' . ucfirst($this->refTableName)
                    . ' hydrator strategy for the ' . $moduleName . ' Module',
                    array(
                        new GenericTag('package', $this->getNamespaceName()),
                    )
                )
            );
        }
    }

    /**
     * Generate an extract method
     */
    protected function addExtractMethod()
    {
        // set action body
        $body = array(
            'return $value->getIdentifier();',
        );
        $body = implode(AbstractGenerator::LINE_FEED, $body);

        // create method
        $method = new MethodGenerator();
        $method->setName('extract');
        $method->setBody($body);
        $method->setParameters(
            array(
                new ParameterGenerator('value'),
            )
        );

        // check for api docs
        if ($this->config['flagAddDocBlocks']) {
            $method->setDocBlock(
                new DocBlockGenerator(
                    'Extract identifier from entity',
                    null,
                    array(
                        new ParamTag(
                            'value',
                            array(
                                $this->entityClass,
                            )
                        ),
                        new ReturnTag(array('string')),
                    )
                )
            );
        }

        // add method
        $this->addMethodFromGenerator($method);
    }

    /**
     * Generate an hydrate method
     */
    protected function addHydrateMethod()
    {
        /** @var TableObject $refTableObject */
        $refTableObject = $this->tableObjects[$this->refTableName];

        // set action body
        $body   = array();
        $body[] = '$' . $this->refTableName . ' = new '
            . $this->entityClass . '();';
        $body[] = '$' . $this->refTableName . '->exchangeArray(';
        $body[] = '    array(';

        /** @var ColumnObject $column */
        foreach ($refTableObject->getColumns() as $column) {
            $body[] = '        \'' . $column->getName() . '\' => $data[\''
                . $this->refTableName . '.' . $column->getName() . '\'],';
        }

        $body[] = '    )';
        $body[] = ');';
        $body[] = '';
        $body[] = 'return $' . $this->refTableName . ';';

        $body = implode(AbstractGenerator::LINE_FEED, $body);

        // create method
        $method = new MethodGenerator();
        $method->setName('hydrate');
        $method->setBody($body);
        $method->setParameters(
            array(
                new ParameterGenerator(
                    'value'
                ),
                new ParameterGenerator(
                    'data', 'array', array()
                ),
            )
        );

        // check for api docs
        if ($this->config['flagAddDocBlocks']) {
            $method->setDocBlock(
                new DocBlockGenerator(
                    'Hydrate an entity by populating data',
                    null,
                    array(
                        new ParamTag(
                            'value'
                        ),
                        new ParamTag(
                            'data',
                            array(
                                'array',
                            )
                        ),
                        new ReturnTag(array($this->entityClass)),
                    )
                )
            );
        }

        // add method
        $this->addMethodFromGenerator($method);
    }

}