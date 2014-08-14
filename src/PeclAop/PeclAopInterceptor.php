<?php
/*
 * This file is part of the `aop-io/pecl-aop-interceptor` package.
 *
 * (c) Nicolas Tallefourtane <dev@nicolab.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code or visit http://aop.io
 *
 * @copyright Nicolas Tallefourtane <http://nicolab.net>
 */

namespace PeclAop;

use
    \AopJoinPoint,
    Aop\Aop,
    Aop\Exception\KindException,
    Aop\Exception\PointcutException,
    Aop\Weaver\Interceptor,
    Aop\Advice\LazyAdvice,
    Aop\Advice\AdviceInterface,
    Aop\JoinPoint\JoinPoint,
    Aop\Pointcut\PointcutInterface,
    Aop\Pointcut\Pointcut
;

/**
 * PeclAopInterceptor provides an abstraction layer for "PECL AOP PHP extension"
 * with many features to go further in the handling of the AOP with PHP.
 *
 * @see \Aop\Weaver\Interceptor
 *
 * @author Nicolas Tallefourtane <dev@nicolab.net>
 */
class PeclAopInterceptor extends Interceptor
{
    /**
     * The context
     *
     * @var array
     *   [<index> => [
     *     'binder'   => <Closure>, // references of callbacks to execute when weaving
     *     'called'   => <bool>, // status of advice execution (called or not called)
     *     'enabled'  => <bool>, // is enabled or disabled
     *     'selector' => <string>, current selector
     *     'pointcut' => <PointcutInterface>, // instance of PointcutInterface
     *     'advice'   => <AdviceInterface>, instance of AdviceInterface
     *   ]]
     */
    private static $context = [];


    /**
     * @inheritdoc
     *
     * When the weaver is completely deactivated, it's the "PECL AOP extension" which is disabled.
     * @see \Aop\Weaver\WeaverInterface::isEnabled()
     */
    public function isEnabled($index = null, $selector = null)
    {
        if(null === $index && null === $selector) {
            return (bool)ini_get('aop.enable');
        }

        // if by index (the first index is 1)
        if($index) {
            return self::$context[ $index ]['enabled'];
        }

        $enabled = null;

        foreach (self::$context as $index => $opt) {

            if($selector == $opt['selector']) {
                $enabled = ((true === $opt['enabled']) ? true : false);
            }
        }

        return $enabled;
    }

    /**
     * @inheritdoc
     * @see \Aop\Weaver\WeaverInterface::enable()
     * @return PeclAopInterceptor The current instance.
     */
    public function enable($index = null, $selector = null)
    {
        if(null === $index && null === $selector) {
            return $this->setWeaving(self::ENABLE);
        }

        // if by index (the first index is 1)
        if ($index) {

            self::$context[$index]['enabled'] = true;

            // make that the binder (container of callback) bind the advice
            $this->doBindAdvice($index);

            return $this;
        }

        foreach (self::$context as $index => $opt) {

            if ($selector == $opt['selector']) {

                self::$context[$index]['enabled'] = true;

                // make that the binder (container of callback) bind the advice
                $this->doBindAdvice($index);
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @see \Aop\Weaver\WeaverInterface::disable()
     * @return PeclAopInterceptor The current instance.
     */
    public function disable($index = null, $selector = null)
    {
        if(null === $index && null === $selector) {
            return $this->setWeaving(self::DISABLE);
        }

        // if by index (the first index is 1)
        if ($index) {

            self::$context[$index]['enabled'] = false;

            // make that the binder (container of callbacks) bind the original code
            $this->doBindOriginalCode($index);

            return $this;
        }

        foreach (self::$context as $index => $opt) {

            if ($selector == $opt['selector']) {

                self::$context[$index]['enabled'] = false;

                // make that the binder (container of callbacks) bind the original code
                $this->doBindOriginalCode($index);
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @see \Aop\Weaver\WeaverInterface::getPointcut()
     */
    public function getPointcut($index)
    {
        return self::$context[$index]['pointcut'];
    }

    /**
     * @inheritdoc
     * @see \Aop\Weaver\WeaverInterface::getIndexOfSelector()
     */
    public function getIndexOfSelector($selector, $status = WeaverInterface::ENABLE)
    {
        $idx = [];

        if(null !== $status) {
            $status = ($status === WeaverInterface::ENABLE) ? true : false;
        }

        foreach (self::$context as $index => $opt) {

            if ($selector == $opt['selector']
                AND (null === $status OR $status === $opt['enabled']))
            {
                $idx[] = $index;
            }
        }

        return $idx;
    }

    /**
     * @inheritdoc
     * @see \Aop\Weaver\WeaverInterface::addBefore()
     */
    public function addBefore(Pointcutinterface $pointcut, AdviceInterface $advice,
                              array $options = [])
    {
        aop_add_before(
            $pointcut->getSelector(),
            $this->createBinder($pointcut, $advice, $options)
        );

        return $this->lastIndex;
    }

    /**
     * @inheritdoc
     * @see \Aop\Weaver\WeaverInterface::addAround()
     */
    public function addAround(Pointcutinterface $pointcut, AdviceInterface $advice,
                              array $options = [])
    {
        aop_add_around(
            $pointcut->getSelector(),
            $this->createBinder($pointcut, $advice, $options)
        );

        return $this->lastIndex;
    }

    /**
     * @inheritdoc
     * @see \Aop\Weaver\WeaverInterface::addAfter()
     */
    public function addAfter(Pointcutinterface $pointcut, AdviceInterface $advice,
                             array $options = [])
    {
        aop_add_after(
            $pointcut->getSelector(),
            $this->createBinder($pointcut, $advice, $options)
        );

        return $this->lastIndex;
    }

    /**
     * @inheritdoc
     * @see \Aop\Weaver\WeaverInterface::addAfterThrow()
     */
    public function addAfterThrow(PointcutInterface $pointcut, AdviceInterface $advice,
                                  array $options = [])
    {
        aop_add_after_throwing(
            $pointcut->getSelector(),
            $this->createBinder($pointcut, $advice, $options)
        );

        return $this->lastIndex;
    }

    /**
     * @inheritdoc
     * @see \Aop\Weaver\WeaverInterface::addAfterReturn()
     */
    public function addAfterReturn(PointcutInterface $pointcut, AdviceInterface $advice,
                                   array $options = [])
    {
        // add in weaver of aop-php extension
        aop_add_after_returning(
            $pointcut->getSelector(),
            $this->createBinder($pointcut, $advice, $options)
        );

        return $this->lastIndex;
    }

    /**
     * Resolve the `JoinPoint`
     *
     * @see PeclAopInterceptor::resolveKind()
     *
     * @param  int                     $index   Index of `PeclAopInterceptor::$context`.
     * @param  \AopJoinPoint            $jp     Join point provided by PECL AOP
     *
     * @return \Aop\JoinPoint\JoinPoint The join point.
     *  The kind of `JoinPoint` depends on the context of the aspect.
     *
     * @throws \Aop\Exception\KindException   If the kind of advice is invalid.
     */
    protected function resolveJoinPoint($index, AopJoinPoint $jp)
    {
        // create an instance of JointPoint (kind resolved)
        return $this->createJoinPoint(

            // kind
            $this->resolveKind($jp->getKindOfAdvice()),

            // Pointcut instance
            self::$context[$index]['pointcut']->setPointcut( $jp->getPointcut() ),

            // support of JoinPoint provided by the interceptor
            new JoinPointSupportInterceptor($jp)
        );
    }

    /**
     * Resolve a kind provided by `AopJoinPoint` to a kind for AOP.io API.
     *
     * @see \Aop\KindConstantInterface
     * @see PeclAopInterceptor::resolveJoinPoint()
     *
     * @param  int  $kind  AOP_KIND_*
     * @return int  The kind
     * @throws \Aop\Exception\KindException If the kind is invalid.
     */
    protected function resolveKind($kind)
    {
        $kinds = [
            AOP_KIND_BEFORE                => Aop::KIND_BEFORE,
            AOP_KIND_AFTER                 => Aop::KIND_AFTER,
            AOP_KIND_AROUND                => Aop::KIND_AROUND,

            AOP_KIND_PROPERTY              => Aop::KIND_PROPERTY,
            AOP_KIND_FUNCTION              => Aop::KIND_FUNCTION,
            AOP_KIND_METHOD                => Aop::KIND_METHOD,
            AOP_KIND_READ                  => Aop::KIND_READ,
            AOP_KIND_WRITE                 => Aop::KIND_WRITE,

            AOP_KIND_AROUND_WRITE_PROPERTY => Aop::KIND_AROUND_PROPERTY_WRITE,
            AOP_KIND_AROUND_READ_PROPERTY  => Aop::KIND_AROUND_PROPERTY_READ,
            AOP_KIND_BEFORE_WRITE_PROPERTY => Aop::KIND_BEFORE_PROPERTY_WRITE,
            AOP_KIND_BEFORE_READ_PROPERTY  => Aop::KIND_BEFORE_PROPERTY_READ,
            AOP_KIND_AFTER_WRITE_PROPERTY  => Aop::KIND_AFTER_PROPERTY_WRITE,
            AOP_KIND_AFTER_READ_PROPERTY   => Aop::KIND_AFTER_PROPERTY_READ,

            AOP_KIND_BEFORE_METHOD         => Aop::KIND_BEFORE_METHOD,
            AOP_KIND_AFTER_METHOD          => Aop::KIND_AFTER_METHOD,
            AOP_KIND_AROUND_METHOD         => Aop::KIND_AROUND_METHOD,

            AOP_KIND_BEFORE_FUNCTION       => Aop::KIND_BEFORE_FUNCTION,
            AOP_KIND_AFTER_FUNCTION        => Aop::KIND_AFTER_FUNCTION,
            AOP_KIND_AROUND_FUNCTION       => Aop::KIND_AROUND_FUNCTION,

            // undefined PECL AOP constants
            836                            => Aop::KIND_AFTER_METHOD,
            580                            => Aop::KIND_AFTER_METHOD_RETURN,
            324                            => Aop::KIND_AFTER_METHOD_THROW,

            900                            => Aop::KIND_AFTER_FUNCTION,
            644                            => Aop::KIND_AFTER_FUNCTION_RETURN,
            388                            => Aop::KIND_AFTER_FUNCTION_THROW,

            820                            => Aop::KIND_AFTER_PROPERTY_WRITE,
            812                            => Aop::KIND_AFTER_PROPERTY_READ,
        ];

        if(!array_key_exists($kind, $kinds) OR !Aop::isValidKind($kinds[$kind])) {
            throw new KindException('The kind (' . $kind . ') is invalid.');
        }

        return $kinds[$kind];
    }

    /**
     * Create a callback which bind the advice in the weaver.
     *
     * @param \Aop\Pointcut\PointcutInterface   $pointcut   The pointcut instance containing
     *                                                      the selector.
     *
     * @param \Aop\Advice\AdviceInterface $advice           The callback to invoke
     *                                                      if pointcut is triggered.
     *
     * @param  array                            $options    An array of options for the advice.
     *
     * @return \Closure                          The callback (advice) for the weaver.
     *
     * @throws \Aop\Exception\PointcutException  If the pointcut does not contain the selector.
     */
    protected function createBinder(PointcutInterface $pointcut, AdviceInterface $advice,
                                    array $options = [])
    {
        // assign the index of this
        $this->lastIndex++;
        $index = $this->lastIndex;

        // if options for the advice
        if(!empty($options['advice'])) {
            $advice->addOptions($options['advice']);
        }

        if(!$pointcut->getSelector()) {
            throw new PointcutException('The instance of the pointcut must contain the selector.');
        }

        // add the advice in the queue
        self::$context[$index] = [
            'advice'   => $advice,
            'pointcut' => $pointcut,
            'binder'   => null,
            'called'   => false,
            'enabled'  => true,
        ];

        // create the reference of the context to add to the weaver of aop-php extension
        $context = &self::$context;

        // add the advice in the binder (container of callback) and bind the advice
        $this->doBindAdvice($index);

        return function (AopJoinPoint $jp) use ($index, &$context) {

            // change the status
            $context[$index]['called'] = true;

            // Resolve AopJoinPoint to JoinPoint
            // and execute the registered callback
            // for this pointcut (the advice or the original code)
            return $context[$index]['binder']($this->resolveJoinPoint($index, $jp));
        };
    }

    /**
     * Make that the binder (container of callbacks) bind the original code.
     *
     * @param  int           $index  Index of `PeclAopInterceptor::$context`.
     * @return PeclAopInterceptor The current instance.
     */
    private function doBindOriginalCode($index)
    {
        // [index][selector]
        self::$context[$index]['binder'] = function (JoinPoint $jp) {

            $kind = $jp->getKind();

            if(in_array($kind, [
                Aop::KIND_AFTER_FUNCTION
                OR Aop::KIND_AFTER_FUNCTION_RETURN
                OR Aop::KIND_AFTER_FUNCTION_THROW
                OR Aop::KIND_AFTER_METHOD
                OR Aop::KIND_AFTER_METHOD_RETURN
                OR Aop::KIND_AFTER_METHOD_THROW
            ]))
            {
                $jp->getReturnValue();
            }

            if(in_array($kind, [
                Aop::KIND_AROUND_FUNCTION,
                Aop::KIND_AROUND_METHOD,
            ]))
            {
                $jp->proceed();
            }
        };

        return $this;
    }

    /**
     * Make that the binder (container of callbacks) bind the advice.
     *
     * @param  int           $index  Index of `PeclAopInterceptor::$context`.
     * @return PeclAopInterceptor The current instance.
     */
    private function doBindAdvice($index)
    {
        $context = &self::$context;

        self::$context[$index]['binder'] = function (JoinPoint $jp) use ($index, &$context) {
            return $context[$index]['advice']($jp);
        };

        return $this;
    }

    /**
     * Enable or disable the weaving (PECL AOP extension).
     *
     * @param  bool|int $status  `true` or `1` (\Aop\Weaver\Weaver::ENABLE) to activate the weaver,
     *                           `false` or `0` (\Aop\Weaver\Weaver::DISABLE) to disable the weaver.
     *
     * @return PeclAopInterceptor The current instance.
     */
    private function setWeaving($status)
    {
        ini_set('aop.enable', (int) $status);

        return $this;
    }
}
