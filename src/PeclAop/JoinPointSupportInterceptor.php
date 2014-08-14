<?php
/*
 * This file is part of the `aop-io/pecl-aop-interceptor` package.
 *
 * (c) Nicolas Tallefourtane <dev@nicolab.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit http://aop.io
 *
 * @copyright Nicolas Tallefourtane http://nicolab.net
 */

namespace PeclAop;

use
    \AopJoinPoint,
    Aop\Aop,
    Aop\Exception\JoinPointException,
    Aop\JoinPoint\Support\JoinPointSupportInterceptorInterface,
    Aop\JoinPoint\Traits
;

/**
 * Provides an abstraction layer of all kind of join point handled by PHP extension "PECL AOP".
 *
 * @see \Aop\JoinPoint\Support\JoinPointSupportInterceptorInterface
 * @see \PeclAop\PeclAopInterceptor
 *
 * @author Nicolas Tallefourtane <dev@nicolab.net>
 */
class JoinPointSupportInterceptor implements JoinPointSupportInterceptorInterface
{
    use
        Traits\ReflectionClassTrait,
        Traits\ReflectionFunctionTrait,
        Traits\ReflectionMethodTrait,
        Traits\ReflectionPropertyTrait
    ;

    /**
     * The join point passed by "PECL AOP" extension.
     * @var AopJoinPoint
     */
    protected $patch;

    /**
     * Constructor.
     *
     * @param AopJoinPoint &$joinPoint
     */
    public function __construct(&$joinPoint)
    {
        if (!$joinPoint instanceof AopJoinPoint) {

            if (is_object($joinPoint)) {
                $typePassed = 'instance of "'.get_class($joinPoint).'"';
            } else {
                $typePassed = '"'.gettype($joinPoint).'"';
            }

            throw new InterceptorException(
                'The join point passed to the PECL AOP interceptor must be an instance of
                "\AopJoinPoint", '.$typePassed.' given.'
            );
        }

        $this->patch = $joinPoint;
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\JoinPointSupportInterceptorInterface::getPatch()
     */
    public function getPatch()
    {
        return $this->patch;
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\KindSupportInterface::getKind()
     */
    public function getKind()
    {
        return Aop::getWeaver()->getInterceptor()->resolveKind($this->patch->getKindOfAdvice());
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\PointcutSupportInterface::getPointcut()
     */
    public function getPointcut()
    {
        return $this->patch->getPointcut();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\ArgsGetterSupportInterface::getArgs()
     */
    public function getArgs()
    {
        return $this->patch->getArguments();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\ArgsSetterSupportInterface::setArgs()
     */
    public function setArgs(array $args)
    {
        return $this->patch->setArguments($args);
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\ClassSupportInterface::getClassName()
     */
    public function getClassName()
    {
        return $this->patch->getClassName();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\ClassSupportInterface::getObject()
     */
    public function getObject()
    {
        return $this->patch->getObject();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\PropertySupportInterface::getPropertyName()
     */
    public function getPropertyName()
    {
        return $this->patch->getPropertyName();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\PropertyValueGetterSupportInterface::getPropertyValue()
     */
    public function getPropertyValue()
    {
        return $this->patch->getAssignedValue();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\PropertyValueSetterSupportInterface::setPropertyValue()
     */
    public function setPropertyValue($value)
    {
        return $this->patch->setAssignedValue($value);
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\MethodSupportInterface::getMethodName()
     */
    public function getMethodName()
    {
        return $this->patch->getMethodName();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\FunctionSupportInterface::getFunctionName()
     */
    public function getFunctionName()
    {
        return $this->patch->getFunctionName();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\ExceptionGetterSupportInterface::getException()
     */
    public function getException()
    {
        return $this->patch->getException();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\ReturnValueGetterSupportInterface::getReturnValue()
     */
    public function &getReturnValue()
    {
        return $this->patch->getReturnedValue();
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\ReturnValueSetterSupportInterface::setReturnValue()
     */
    public function setReturnValue($value)
    {
        return $this->patch->setReturnedValue($value);
    }

    /**
     * @inheritdoc
     * @see \Aop\JoinPoint\Support\ProceedSupportInterface::proceed()
     */
    public function proceed()
    {
        return $this->patch->process();
    }

}
