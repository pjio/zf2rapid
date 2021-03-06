<?php
/**
 * ZF2rapid - Zend Framework 2 Rapid Development Tool
 *
 * @link      https://github.com/ZFrapid/zf2rapid
 * @copyright Copyright (c) 2014 - 2015 Ralf Eggert
 * @license   http://opensource.org/licenses/MIT The MIT License (MIT)
 */
namespace ZF2rapid\Task\GenerateClass;

use ZF2rapid\Generator\HydratorClassGenerator;

/**
 * Class GenerateHydratorClass
 *
 * @package ZF2rapid\Task\GenerateClass
 */
class GenerateHydratorClass extends AbstractGenerateClass
{
    /**
     * Process the command
     *
     * @return integer
     */
    public function processCommandTask()
    {
        if (isset($this->params->hydratorClassName)) {
            $hydrator = $this->params->hydratorClassName;
        } else {
            $hydrator = $this->params->paramHydrator;
        }

        $result = $this->generateClass(
            $this->params->hydratorDir,
            $hydrator,
            'hydrator',
            new HydratorClassGenerator(
                $this->params->config,
                $this->params->paramBaseHydrator
            )
        );

        return $result == true ? 0 : 1;
    }
}