<?php
use PHPUnit\Framework\TestCase;

require_once("Redis.php");

/**
 * Class TestCaseCTT
 *
 * PHPUnit TestCase extension that provides additional methods for accessing private methods/member variables,
 * mocking Redis methods, and defining commonly used constant variables.
 */
class TestCaseCTT extends TestCase
{
    const TEST_USER_NAME1 = 'userName1';
    const TEST_USER_NAME2 = 'userName2';
    const TEST_CHANNEL_ID = '12345';
    const TEST_GAME_BOARD = ":x::rooster::rooster:\n:rooster::rooster::rooster:\n:rooster::rooster::rooster:\n"; //with move on square #1
    const TEST_INSTRUCTION_BOARD = ":one::two::three:\n:x::five::six:\n:seven::eight::nine:\n"; //with example 'X' move on square #4

    /**
     * Use Reflection class to access a protected class property.
     *
     * @param $object
     * @param $protectedPropertyName
     * @return ReflectionProperty
     */
    protected function getReflectionProperty(&$object, $protectedPropertyName)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $reflectionProperty = $reflection->getProperty($protectedPropertyName);
        $reflectionProperty->setAccessible(true);
        return $reflectionProperty;
    }

    /**
     * Set a protected/private class property to a passed-in value.
     *
     * @param $object
     * @param $protectedPropertyName
     * @param $protectedPropertyValue
     */
    public function setProtectedValue(&$object, $protectedPropertyName, $protectedPropertyValue)
    {
        $reflectionProperty = $this->getReflectionProperty($object, $protectedPropertyName);
        $reflectionProperty->setValue($object, $protectedPropertyValue);
    }

    /**
     * Retrieve the value of a protected/private class property.
     *
     * @param $object
     * @param $protectedPropertyName
     * @return mixed
     */
    public function getProtectedValue(&$object, $protectedPropertyName)
    {
        $reflectionProperty = $this->getReflectionProperty($object, $protectedPropertyName);
        return $reflectionProperty->getValue($object);
    }

    /**
     * Invoke a private/protected method of a class with the passed-in parameters
     * and return the result.
     *
     * @param $object
     * @param $methodName
     * @param array $parameters
     * @return mixed
     */
    public function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Retrieve a mock object for the Redis class.
     *
     * @param $redisStateMethod
     * @param $numCalls
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    public function getRedisMock($redisStateMethod, $numCalls)
    {
        $redisMock = $this->getMockBuilder('RedisState')
            ->setMethods(['getRedis', 'setRedis', 'deleteRedis'])
            ->getMock();

        $redisMock->expects($this->exactly($numCalls))
            ->method($redisStateMethod);

        return $redisMock;
    }
}
