<?php

namespace Ultra\ErrorManager\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass; // Import UsesClass
use Ultra\ErrorManager\Exceptions\UltraErrorException; // Class under test
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider; // Import ServiceProvider
use Ultra\ErrorManager\Tests\UltraTestCase; // Use base TestCase
use Exception; // For previous exception testing
use Throwable; // For previous exception testing

/**
 * 📜 Oracode Unit Test: UltraErrorExceptionTest
 *
 * Tests the UltraErrorException class, ensuring that constructor arguments
 * are assigned correctly and getter/setter methods function as expected.
 *
 * @package         Ultra\ErrorManager\Tests\Unit\Exceptions
 * @version         0.1.1 // Added UsesClass for ServiceProvider.
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 *
 * 🎯 Tests the UltraErrorException custom exception class.
 * 🧱 Creates instances of the exception and asserts property values via getters.
 * 📡 No external communication.
 * 🧪 Simple instantiation and assertion, no mocking needed.
 */
#[CoversClass(UltraErrorException::class)]
#[UsesClass(UltraErrorManagerServiceProvider::class)] // Add this line
class UltraErrorExceptionTest extends UltraTestCase
{
    /**
     * 🎯 Test [constructor]: Sets properties correctly with all arguments provided.
     * 🧪 Strategy: Instantiate with all params, use getters to verify values.
     */
    #[Test]
    public function constructor_sets_properties_correctly_with_all_arguments(): void
    {
        // Arrange
        $message = 'Test error message';
        $code = 418;
        $previous = new Exception('Previous exception');
        $stringCode = 'TEAPOT_ERROR';
        $context = ['key' => 'value', 'id' => 123];

        // Act
        $exception = new UltraErrorException($message, $code, $previous, $stringCode, $context);

        // Assert
        $this->assertInstanceOf(UltraErrorException::class, $exception);
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($stringCode, $exception->getStringCode());
        $this->assertEquals($context, $exception->getContext());
    }

    /**
     * 🎯 Test [constructor]: Sets properties correctly with default/null arguments.
     * 🧪 Strategy: Instantiate with minimal params, verify default/null values via getters.
     */
    #[Test]
    public function constructor_sets_properties_correctly_with_defaults(): void
    {
        // Arrange
        $message = 'Minimal message';

        // Act
        $exception = new UltraErrorException($message);

        // Assert
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertNull($exception->getStringCode());
        $this->assertEquals([], $exception->getContext());
    }

    /**
     * 🎯 Test [getters]: Return correct values after instantiation.
     * 🧪 Strategy: Instantiate, call getters, assert returned values.
     */
    #[Test]
    public function getters_return_correct_values(): void
    {
        // Arrange
        $stringCode = 'GETTER_TEST';
        $context = ['status' => 'testing'];
        $exception = new UltraErrorException('Getter test', 1, null, $stringCode, $context);

        // Act & Assert
        $this->assertEquals($stringCode, $exception->getStringCode());
        $this->assertEquals($context, $exception->getContext());
    }

    /**
     * 🎯 Test [setStringCode]: Updates the stringCode property correctly.
     * 🧪 Strategy: Instantiate, call setter, use getter to verify the change.
     */
    #[Test]
    public function setStringCode_updates_property(): void
    {
        // Arrange
        $initialStringCode = 'INITIAL_CODE';
        $newStringCode = 'UPDATED_CODE';
        $exception = new UltraErrorException('Setter test', 0, null, $initialStringCode);
        $this->assertEquals($initialStringCode, $exception->getStringCode());

        // Act
        $returnValue = $exception->setStringCode($newStringCode);

        // Assert
        $this->assertSame($exception, $returnValue); // Check fluent interface
        $this->assertEquals($newStringCode, $exception->getStringCode()); // Check update

        // Test setting to null
        $exception->setStringCode(null);
        $this->assertNull($exception->getStringCode());
    }

} // End class UltraErrorExceptionTest