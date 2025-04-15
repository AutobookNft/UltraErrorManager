<?php

/**
 * 📜 Oracode Unit Test: ErrorManagerTest (UEM)
 *
 * @package         Ultra\ErrorManager\Tests\Unit
 * @version         0.1.0 // Initial tests for Constructor, Handlers, Runtime Errors
 * @author          Padmin D. Curtis (Generated for Fabio Cherici)
 * @copyright       2024 Fabio Cherici
 * @license         MIT
 */

namespace Ultra\ErrorManager\Tests\Unit;

// Laravel & PHP Core
use Illuminate\Contracts\Translation\Translator as TranslatorContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // Per test risposte
use Illuminate\Http\RedirectResponse; // Per test risposte
use Throwable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;

// PHPUnit
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

// UEM Core & Dependencies
use Ultra\ErrorManager\ErrorManager; // Class under test
use Ultra\ErrorManager\Interfaces\ErrorManagerInterface; // Usato da ErrorManager
use Ultra\ErrorManager\Interfaces\ErrorHandlerInterface; // Per mock handler
use Ultra\ErrorManager\Exceptions\UltraErrorException; // Eccezione UEM
use Ultra\ErrorManager\Providers\UltraErrorManagerServiceProvider;
use Ultra\UltraLogManager\UltraLogManager; // Dipendenza principale ULM
use Ultra\ErrorManager\Tests\UltraTestCase; // Base test case per ambiente Laravel

/**
 * 🎯 Purpose: Unit tests for the core Ultra\ErrorManager\ErrorManager class.
 *    Verifies constructor logic, handler registration, runtime error definition,
 *    and the core error handling pipeline in isolation using mocked dependencies.
 *
 * 🧪 Test Strategy: Pure unit tests using Mockery.
 *    - Mocks: UltraLogManager, TranslatorContract, Request, ErrorHandlerInterface.
 *    - Config injected as array.
 *    - Focuses on method logic, dependency interaction, and state changes.
 *
 * @package Ultra\ErrorManager\Tests\Unit
 */
#[CoversClass(ErrorManager::class)]
#[UsesClass(UltraErrorException::class)]    
#[UsesClass(UltraErrorManagerServiceProvider::class)]
class ErrorManagerTest extends UltraTestCase
{
    use MockeryPHPUnitIntegration; // Gestisce Mockery::close()

    // --- Mocks for Dependencies ---
    protected UltraLogManager&MockInterface $loggerMock;
    protected TranslatorContract&MockInterface $translatorMock;
    protected Request&MockInterface $requestMock;
    protected array $testConfig; // Array per la configurazione iniettata

    // --- Instance of the Class Under Test ---
    protected ErrorManager $manager; // Non interfaccia qui, ma classe concreta

        /**
     * ⚙️ Set up the test environment before each test.
     * Creates mocks for dependencies and instantiates the ErrorManager.
     */
    protected function setUp(): void
    {
        parent::setUp(); // Call parent (UltraTestCase) setUp

        // 1. Create Mocks
        $this->loggerMock = Mockery::mock(UltraLogManager::class)->shouldIgnoreMissing();
        $this->translatorMock = Mockery::mock(TranslatorContract::class);
        $this->requestMock = Mockery::mock(Request::class);

        // 2. Define Default Test Config
        $this->testConfig = [
            'default_handlers' => [],
            'errors' => [
                'STATIC_ERROR' => [
                    'type' => 'error',
                    'message' => 'Static error message',
                    'http_status_code' => 400,
                ],
                // === CORREZIONE: Rendi ANOTHER_ERROR esplicitamente non bloccante ===
                'ANOTHER_ERROR' => [
                    'type' => 'warning',
                    'blocking' => 'not' // Aggiunto per il test non bloccante
                ],
                // === FINE CORREZIONE ===
            ],
            'fallback_error' => ['type' => 'critical', 'message' => 'Fatal fallback message'],
            'ui' => ['generic_error_message' => 'error-manager::errors.user.fallback_error']
        ];

        // Set up generic expectation for the fallback translator key
        $this->translatorMock->shouldReceive('get')
            ->zeroOrMoreTimes()
            ->with('error-manager::errors.user.fallback_error', Mockery::any())
            ->andReturn('Generic fallback user message from mock.');

        // Instantiate the base Manager
        $this->manager = new ErrorManager(
            $this->loggerMock,
            $this->translatorMock,
            $this->requestMock,
            $this->testConfig
        );
    }

    // ========================================================================
    // A. Test del Costruttore e Setup Iniziale
    // ========================================================================

    #[Test]
    public function constructor_initializes_correctly_with_dependencies(): void
    {
    
    // Arrange
    // Dobbiamo RI-creare il manager QUI per poter mettere l'aspettativa PRIMA
    $loggerMock = Mockery::mock(UltraLogManager::class)->shouldIgnoreMissing();
    $translatorMock = Mockery::mock(TranslatorContract::class);
    $requestMock = Mockery::mock(Request::class);
    $testConfig = ['errors' => [], 'fallback_error' => [], 'ui' => []]; // Config minima

    // --- AGGIUNTA ASPETTATIVA QUI ---
    $loggerMock->shouldReceive('info')
        ->once()
        ->with(Mockery::pattern('/^UltraErrorManager Initialized/'), Mockery::any());

    // Act
    $manager = new ErrorManager($loggerMock, $translatorMock, $requestMock, $testConfig);

    // Assert
    $this->assertInstanceOf(ErrorManager::class, $manager);
    // Mockery verifica il log
    }

    // ========================================================================
    // B. Test Registrazione Handler (`registerHandler`, `getHandlers`)
    // ========================================================================

    #[Test]
    public function registerHandler_adds_handler_to_registry(): void
    {
        // Arrange
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $initialCount = count($this->manager->getHandlers());

        // Expect debug log for registration
        $this->loggerMock->shouldReceive('debug')
            ->once()
            ->with('Registered error handler', Mockery::on(fn($ctx)=>$ctx['handler_class']===get_class($handlerMock) && $ctx['total_handlers']===$initialCount+1));

        // Act
        $this->manager->registerHandler($handlerMock);

        // Assert
        $handlers = $this->manager->getHandlers();
        $this->assertCount($initialCount + 1, $handlers);
        $this->assertContains($handlerMock, $handlers); // Verifica che l'istanza specifica sia presente
    }

    #[Test]
    public function getHandlers_returns_registered_handlers(): void
    {
        // Arrange
        $handlerMock1 = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock2 = Mockery::mock(ErrorHandlerInterface::class);
        // Ignora i log di registrazione qui
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->twice();
        $this->manager->registerHandler($handlerMock1);
        $this->manager->registerHandler($handlerMock2);

        // Act
        $handlers = $this->manager->getHandlers();

        // Assert
        $this->assertIsArray($handlers);
        $this->assertCount(2, $handlers);
        $this->assertEquals([$handlerMock1, $handlerMock2], $handlers); // Controlla anche l'ordine
    }

    #[Test]
    public function registerHandler_allows_multiple_handlers(): void
    {
        // Arrange
        $handlerMock1 = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock2 = Mockery::mock(ErrorHandlerInterface::class);
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->times(2); // Aspettati 2 log

        // Act
        $this->manager->registerHandler($handlerMock1);
        $this->manager->registerHandler($handlerMock2);

        // Assert
        $this->assertCount(2, $this->manager->getHandlers());
    }

    // ========================================================================
    // C. Test Definizione Errori Runtime (`defineError`, `getErrorConfig`)
    // ========================================================================

    #[Test]
    public function defineError_adds_custom_error_config(): void
    {
        // Arrange
        $errorCode = 'RUNTIME_ERROR';
        $configData = ['type' => 'notice', 'message' => 'Runtime defined message'];
        $this->loggerMock->shouldReceive('debug')->once()->with('Defined runtime error configuration', Mockery::any());

        // Act
        $this->manager->defineError($errorCode, $configData);

        // Assert
        $resolvedConfig = $this->manager->getErrorConfig($errorCode);
        $this->assertIsArray($resolvedConfig);
        $this->assertEquals($configData, $resolvedConfig);
    }

    #[Test]
    public function getErrorConfig_returns_runtime_definition_when_present(): void
    {
        // Arrange
        $errorCode = 'PRIORITY_ERROR';
        $runtimeConfig = ['type' => 'runtime', 'priority' => true];
        $staticConfig = ['type' => 'static']; // Definisci anche una statica per assicurare la priorità

        $this->testConfig['errors'][$errorCode] = $staticConfig; // Simula config statica
         // Crea un nuovo manager con la config aggiornata per questo test
         // (per evitare side effect su altri test che usano la config del setUp)
        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);
        $this->loggerMock->shouldReceive('debug')->with('Defined runtime error configuration', Mockery::any())->once(); // Log da defineError
        $manager->defineError($errorCode, $runtimeConfig); // Definisci a runtime


        // Act
        $resolvedConfig = $manager->getErrorConfig($errorCode);

        // Assert
        $this->assertEquals($runtimeConfig, $resolvedConfig); // Deve restituire quella runtime
    }

    #[Test]
    public function getErrorConfig_returns_static_config_when_runtime_missing(): void
    {
        // Arrange
        $errorCode = 'STATIC_ERROR'; // Esiste solo nella config statica definita nel setUp
        $expectedConfig = $this->testConfig['errors'][$errorCode];

        // Act
        $resolvedConfig = $this->manager->getErrorConfig($errorCode); // Usa il manager del setUp

        // Assert
        $this->assertEquals($expectedConfig, $resolvedConfig);
    }

    #[Test]
    public function getErrorConfig_returns_null_for_undefined_error(): void
    {
        // Arrange
        $errorCode = 'COMPLETELY_UNDEFINED_ERROR';
        // Aspettati il log notice per config non trovata
        $this->loggerMock->shouldReceive('notice')
            ->once()
            ->with('Static error code configuration not found', ['code' => $errorCode]);

        // Act
        $resolvedConfig = $this->manager->getErrorConfig($errorCode);

        // Assert
        $this->assertNull($resolvedConfig);
    }

    // ========================================================================
    // D. Test Risoluzione Configurazione (via handle)
    // ========================================================================

    /**
     * ✅ Test [handle]: Uses direct error code config for JSON request.
     * 🧪 Verifies correct config usage, handler dispatch, and JSON response.
     */
    #[Test]
    public function handle_uses_direct_error_code_when_config_exists_for_JSON_request(): void
    {
        // --- Arrange ---
        $method = 'POST';
        $expectsJson = true;
        $errorCode = 'STATIC_ERROR';
        $context = ['detail' => 'some data'];
        $errorConfig = $this->testConfig['errors'][$errorCode];
        // Il messaggio utente FINALE sarà il fallback generico,
        // ottenuto TRAMITE __() in prepareErrorInfo PRIMA di chiamare formatMessage.
        $expectedUserMessageInResponse = __('error-manager::errors.user.fallback_error');

        // Mock Request
        $this->requestMock->shouldReceive('method')->andReturn($method);
        $this->requestMock->shouldReceive('expectsJson')->andReturn($expectsJson);
        $this->requestMock->shouldReceive('is')->with('api/*')->andReturn($expectsJson);

        // --- CORREZIONE: NESSUNA chiamata a Translator::get è attesa da formatMessage ---
        $this->translatorMock->shouldNotReceive('get');

        // Mock Handler
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')->once()->with($errorCode, $errorConfig, $context, null);
        $this->manager->registerHandler($handlerMock);

        // --- CORREZIONE Log Expectations ---
        $this->loggerMock->shouldReceive('info')->once()->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any());
        // NESSUN log da formatMessage in questo scenario
        $this->loggerMock->shouldReceive('debug')->once()->with("UEM Handlers dispatched", ['resolved_code' => $errorCode]);
        $this->loggerMock->shouldReceive('info')->once()->with('UEM Returning JSON error response', Mockery::any());
        // --- FINE CORREZIONE Log Expectations ---

        // --- Act ---
        $response = $this->manager->handle($errorCode, $context, null, false);

        // --- Assert ---
        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = $response->getData(true);
        $this->assertEquals($errorCode, $responseData['error']);
        $this->assertEquals($expectedUserMessageInResponse, $responseData['message']); // Verifica fallback
    }

    #[Test]
    public function handle_uses_direct_error_code_when_config_exists_for_HTML_request(): void
    {
        // --- Arrange ---
        $method = 'GET';
        $expectsJson = false;
        $errorCode = 'STATIC_ERROR';
        $context = ['detail' => 'some data'];
        $errorConfig = $this->testConfig['errors'][$errorCode];
        $errorConfig['blocking'] = 'not';
        $this->testConfig['errors'][$errorCode]['blocking'] = 'not';
        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);

        // Mock Request
        $this->requestMock->shouldReceive('method')->andReturn($method);
        $this->requestMock->shouldReceive('expectsJson')->andReturn($expectsJson);
        $this->requestMock->shouldReceive('is')->with('api/*')->andReturn($expectsJson);

        // --- CORREZIONE: NESSUNA chiamata a Translator::get è attesa da formatMessage ---
        $this->translatorMock->shouldNotReceive('get');

        // Mock Handler
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')->once()->with($errorCode, $errorConfig, $context, null);
        $manager->registerHandler($handlerMock);

        // --- CORREZIONE Log Expectations ---
        $this->loggerMock->shouldReceive('info')->once()->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any());
        // NESSUN log da formatMessage in questo scenario
        $this->loggerMock->shouldReceive('debug')->once()->with("UEM Handlers dispatched", ['resolved_code' => $errorCode]);
        $this->loggerMock->shouldReceive('info')->once()->with(Mockery::pattern('/^UEM Handling non-blocking error for HTML request/'), Mockery::any());
        // --- FINE CORREZIONE Log Expectations ---


        // --- Act ---
        $response = $manager->handle($errorCode, $context, null, false);

        // --- Assert ---
        $this->assertNull($response);
    }

    /**
     * ✅ Test [handle]: Falls back to UNDEFINED_ERROR_CODE when direct config missing.
     * 🧪 Verifies fallback logic, context modification, and Translator interaction.
     */
    #[Test]
    public function handle_falls_back_to_UNDEFINED_ERROR_CODE_when_direct_config_missing(): void
    {
        // --- Arrange ---
        $originalErrorCode = 'MISSING_IN_STATIC';
        $fallbackErrorCode = 'UNDEFINED_ERROR_CODE';
        $originalContext = ['original_detail' => 'data'];
        $this->testConfig['errors'][$fallbackErrorCode] = [
            'type' => 'critical',
            'user_message_key' => 'error-manager::errors.user.undefined_error_code', // Chiave per Translator
            'message' => 'Dev message for undefined', // Messaggio dev per handler/log
            'http_status_code' => 500, // Aggiungi status per response JSON
            'blocking' => 'blocking' // Aggiungi blocking per response JSON
        ];
        $fallbackConfig = $this->testConfig['errors'][$fallbackErrorCode];
        $expectedUserMessage = 'Translated undefined error message.'; // Messaggio da Translator

        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);

        // Mock Request (JSON)
        $this->requestMock->shouldReceive('method')->andReturn('POST');
        $this->requestMock->shouldReceive('expectsJson')->andReturn(true);
        $this->requestMock->shouldReceive('is')->with('api/*')->andReturn(true);

        // Mock Translator
        $this->translatorMock->shouldReceive('get')
            ->once()
            ->with(
                $fallbackConfig['user_message_key'],
                Mockery::on(function ($contextArg) use ($originalErrorCode, $originalContext) {
                    return is_array($contextArg) &&
                           isset($contextArg['_original_code']) && $contextArg['_original_code'] === $originalErrorCode &&
                           isset($contextArg['original_detail']) && $contextArg['original_detail'] === $originalContext['original_detail'];
                })
            )
            ->andReturn($expectedUserMessage);

        // Mock Handler
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($fallbackConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')
            ->once()
            ->with(
                $fallbackErrorCode,
                $fallbackConfig,
                Mockery::on(fn($ctx) => isset($ctx['_original_code']) && $ctx['_original_code'] === $originalErrorCode),
                null
            );
        $manager->registerHandler($handlerMock);
        // NESSUNA aspettativa per il log di 'Registered error handler' qui


        // Log specifici
        $this->loggerMock->shouldReceive('info')->once()->with(Mockery::pattern("/^UEM Handling error: \[$originalErrorCode\]/"), Mockery::any());
        $this->loggerMock->shouldReceive('notice')->once()->with('Static error code configuration not found', ['code' => $originalErrorCode]);
        $this->loggerMock->shouldReceive('warning')->once()->with(Mockery::pattern("/^UEM Undefined error code: \[$originalErrorCode\]. Attempting UNDEFINED_ERROR_CODE fallback./"), Mockery::any());
        $this->loggerMock->shouldReceive('debug')->once()->with('UEM Using translated message', ['key' => $fallbackConfig['user_message_key']]);
        $this->loggerMock->shouldReceive('debug')->once()->with("UEM Handlers dispatched", ['resolved_code' => $fallbackErrorCode]);
        $this->loggerMock->shouldReceive('info')->once()->with('UEM Returning JSON error response', Mockery::any());

        // --- Act ---
        $response = $manager->handle($originalErrorCode, $originalContext, null, false);

        // --- Assert ---
        $this->assertInstanceOf(JsonResponse::class, $response);
        $responseData = $response->getData(true);
        $this->assertEquals($fallbackErrorCode, $responseData['error']);
        $this->assertEquals($expectedUserMessage, $responseData['message']);
    }

    /**
     * ✅ Test [handle]: Falls back to 'fallback_error' when UNDEFINED_ERROR_CODE is also missing.
     * 🧪 Verifies the second level of fallback logic.
     */
    #[Test]
    public function handle_falls_back_to_fallback_error_when_UNDEFINED_is_missing(): void
    {
         // --- Arrange ---
        $originalErrorCode = 'MISSING_EVERYWHERE';
        $fallbackErrorCode = 'FALLBACK_ERROR';
        $originalContext = ['info' => 'test'];
        unset($this->testConfig['errors']['UNDEFINED_ERROR_CODE']); // Assicura che manchi
        $this->testConfig['fallback_error']['user_message_key'] = 'error-manager::errors.user.fatal_fallback_failure'; // Usa una chiave traducibile
        $this->testConfig['fallback_error']['http_status_code'] = 500; // Assicura ci sia
        $this->testConfig['fallback_error']['blocking'] = 'blocking'; // Assicura ci sia
        $fallbackConfig = $this->testConfig['fallback_error'];
        $expectedUserMessage = 'Translated fatal fallback message.';

        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);

        // Mock Request (JSON)
        $this->requestMock->shouldReceive('method')->andReturn('GET');
        $this->requestMock->shouldReceive('expectsJson')->andReturn(true);
        $this->requestMock->shouldReceive('is')->with('api/*')->andReturn(true);

        // Mock Translator
        $this->translatorMock->shouldReceive('get')
            ->once()
            ->with(
                $fallbackConfig['user_message_key'],
                 Mockery::on(function ($contextArg) use ($originalErrorCode, $originalContext) {
                     return is_array($contextArg) &&
                           isset($contextArg['_original_code']) &&
                           $contextArg['_original_code'] === $originalErrorCode &&
                           isset($contextArg['info']) && // Verifica chiave originale
                           $contextArg['info'] === $originalContext['info'];
                })
            )
            ->andReturn($expectedUserMessage);

        // Mock Handler
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($fallbackConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')
            ->once()
            ->with(
                $fallbackErrorCode,
                $fallbackConfig,
                Mockery::on(fn($ctx) => isset($ctx['_original_code']) && $ctx['_original_code'] === $originalErrorCode),
                null
            );
        $manager->registerHandler($handlerMock);
         // NESSUNA aspettativa per il log di 'Registered error handler' qui


        // Log specifici
        $this->loggerMock->shouldReceive('info')->once()->with(Mockery::pattern("/^UEM Handling error: \[$originalErrorCode\]/"), Mockery::any());
        $this->loggerMock->shouldReceive('notice')->once()->with('Static error code configuration not found', ['code' => $originalErrorCode]);
        $this->loggerMock->shouldReceive('warning')->once()->with(Mockery::pattern("/^UEM Undefined error code: \[$originalErrorCode\]. Attempting UNDEFINED_ERROR_CODE fallback./"), Mockery::any());
        $this->loggerMock->shouldReceive('notice')->once()->with('Static error code configuration not found', ['code' => 'UNDEFINED_ERROR_CODE']);
        $this->loggerMock->shouldReceive('error')->once()->with("UEM Missing config for UNDEFINED_ERROR_CODE. Trying 'fallback_error'.", ['original_code' => $originalErrorCode]);
        $this->loggerMock->shouldReceive('debug')->once()->with('UEM Using translated message', ['key' => $fallbackConfig['user_message_key']]);
        $this->loggerMock->shouldReceive('debug')->once()->with("UEM Handlers dispatched", ['resolved_code' => $fallbackErrorCode]);
        $this->loggerMock->shouldReceive('info')->once()->with('UEM Returning JSON error response', Mockery::any());

        // --- Act ---
        $response = $manager->handle($originalErrorCode, $originalContext, null, false);

        // --- Assert ---
         $this->assertInstanceOf(JsonResponse::class, $response);
         $responseData = $response->getData(true);
         $this->assertEquals($fallbackErrorCode, $responseData['error']);
         $this->assertEquals($expectedUserMessage, $responseData['message']);
    }

    /**
     * ✅ Test [handle]: Throws fatal exception when all configurations are missing.
     * 🧪 Verifies the ultimate fallback failure scenario.
     */
    #[Test]
    public function handle_throws_fatal_exception_when_all_configs_missing(): void
    {
        // --- Arrange ---
        $originalErrorCode = 'ABSOLUTELY_NOWHERE';
        $context = ['data' => 123];
        // Rimuovi UNDEFINED e fallback dalla config
        unset($this->testConfig['errors']['UNDEFINED_ERROR_CODE']);
        unset($this->testConfig['fallback_error']);

        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);

        // Mock Request (non rilevante per l'eccezione)
        $this->requestMock->shouldReceive('expectsJson')->andReturn(false);
        $this->requestMock->shouldReceive('is')->with('api/*')->andReturn(false);

        // Log specifici attesi prima dell'eccezione
        $this->loggerMock->shouldReceive('info')->once()->with(Mockery::pattern("/^UEM Handling error: \[$originalErrorCode\]/"), Mockery::any());
        $this->loggerMock->shouldReceive('notice')->once()->with('Static error code configuration not found', ['code' => $originalErrorCode]);
        $this->loggerMock->shouldReceive('warning')->once()->with(Mockery::pattern("/^UEM Undefined error code: \[$originalErrorCode\]. Attempting UNDEFINED_ERROR_CODE fallback./"), Mockery::any());
        $this->loggerMock->shouldReceive('notice')->once()->with('Static error code configuration not found', ['code' => 'UNDEFINED_ERROR_CODE']);
        $this->loggerMock->shouldReceive('error')->once()->with("UEM Missing config for UNDEFINED_ERROR_CODE. Trying 'fallback_error'.", ['original_code' => $originalErrorCode]);
        $this->loggerMock->shouldReceive('critical')->once()->with("UEM No 'fallback_error' configuration available. This is fatal.", ['original_code' => $originalErrorCode]);

        // Aspettati l'eccezione FATALE
        $this->expectException(UltraErrorException::class);
        $this->expectExceptionMessageMatches("/FATAL: No error configuration found/");
        $this->expectExceptionCode(500);
        try {
            // --- Act ---
            $manager->handle($originalErrorCode, $context, null, false);
        } catch (UltraErrorException $e) {
             // --- Assert Contestuale nell'eccezione ---
             $this->assertEquals('FATAL_FALLBACK_FAILURE', $e->getStringCode());
             $this->assertArrayHasKey('_original_code', $e->getContext());
             $this->assertEquals($originalErrorCode, $e->getContext()['_original_code']);
            throw $e; // Ri-lancia per soddisfare expectException
        }
    }

    /**
     * Data provider per tipi di richiesta (JSON vs HTML)
     */
    public static function requestTypeProvider(): array
    {
        return [
            'HTML Request' => ['GET', false],
            'JSON Request' => ['POST', true],
            'API Request' => ['PUT', true],
        ];
    }

    // ========================================================================
    // E. Test Preparazione Info Errore (via handle)
    // ========================================================================

    /**
     * Helper per asserire la struttura base di errorInfo passato all'handler.
     * @param array $errorInfo L'array ricevuto dal mock handler.
     * @param string $expectedCode Il codice errore atteso.
     * @param string $expectedType Il tipo errore atteso.
     * @param string $expectedBlocking Il livello blocking atteso.
     */
    #[Test]
    private function assertBasicErrorInfoStructure(array $errorInfo, string $expectedCode, string $expectedType, string $expectedBlocking): void
    {
        $this->assertArrayHasKey('error_code', $errorInfo);
        $this->assertEquals($expectedCode, $errorInfo['error_code']);

        $this->assertArrayHasKey('type', $errorInfo);
        $this->assertEquals($expectedType, $errorInfo['type']);

        $this->assertArrayHasKey('blocking', $errorInfo);
        $this->assertEquals($expectedBlocking, $errorInfo['blocking']);

        $this->assertArrayHasKey('message', $errorInfo); // Messaggio Dev
        $this->assertIsString($errorInfo['message']);

        $this->assertArrayHasKey('user_message', $errorInfo); // Messaggio User
        $this->assertIsString($errorInfo['user_message']);

        $this->assertArrayHasKey('http_status_code', $errorInfo);
        $this->assertIsInt($errorInfo['http_status_code']);

        $this->assertArrayHasKey('context', $errorInfo);
        $this->assertIsArray($errorInfo['context']);

        $this->assertArrayHasKey('display_mode', $errorInfo);
        $this->assertIsString($errorInfo['display_mode']);

        $this->assertArrayHasKey('timestamp', $errorInfo);
        $this->assertIsString($errorInfo['timestamp']); // Verifica formato ISO se necessario

        $this->assertArrayHasKey('exception', $errorInfo); // Chiave exception deve esistere (anche se null)
    }

    #[Test]
    public function handle_prepares_correct_errorInfo_structure_without_exception(): void
    {
        // --- Arrange ---
        $errorCode = 'BASIC_INFO_TEST';
        $context = ['file' => 'test.txt'];
        $errorConfig = [
            'type' => 'warning', 'blocking' => 'not', 'http_status_code' => 400,
            'dev_message' => 'Basic dev message for :file',
            'msg_to' => 'toast',
        ];
        $this->testConfig['errors'][$errorCode] = $errorConfig;
        // Il messaggio utente atteso è quello che __() restituirebbe
        $expectedUserMessageInResponse = __('error-manager::errors.user.fallback_error');

        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);

        // Mock Request (HTML, non bloccante)
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(false);
        $this->requestMock->shouldReceive('is')->once()->with('api/*')->andReturn(false);

        // --- CORREZIONE FINALE: NESSUNA chiamata a Translator::get è attesa ---
        $this->translatorMock->shouldNotReceive('get');

        // Mock Handler per catturare gli argomenti
        $capturedCode = null; $capturedConfig = null; $capturedContext = null; $capturedException = null;
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')
            ->once()
            ->withArgs(function ($code, $config, $ctx, $ex) use (&$capturedCode, &$capturedConfig, &$capturedContext, &$capturedException) {
                $capturedCode = $code; $capturedConfig = $config;
                $capturedContext = $ctx; $capturedException = $ex;
                return true;
            });
        $manager->registerHandler($handlerMock);

        // Log Expectations corrette per questo flusso
        $this->loggerMock->shouldReceive('info')->once()->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any());
        // Nessun log 'debug' o 'warning' da formatMessage per user_message
        $this->loggerMock->shouldReceive('debug')->once()->with("UEM Handlers dispatched", ['resolved_code' => $errorCode]);
        $this->loggerMock->shouldReceive('info')->once()->with(Mockery::pattern('/^UEM Handling non-blocking error for HTML request/'), Mockery::any());


        // --- Act ---
        $response = $manager->handle($errorCode, $context, null, false);

        // --- Assert ---
        $this->assertNull($response);
        $this->assertEquals($errorCode, $capturedCode);
        $this->assertEquals($errorConfig, $capturedConfig);
        $this->assertEquals($context, $capturedContext);
        $this->assertNull($capturedException);
        // Non possiamo asserire il messaggio utente direttamente dal capturedConfig
        // perché viene popolato da prepareErrorInfo prima di chiamare l'handler
    }

    #[Test]
    public function handle_prepares_correct_exception_info_when_exception_present(): void
    {
         // --- Arrange ---
         $errorCode = 'EXCEPTION_TEST'; $context = ['user_id' => 1];
         $exceptionMessage = 'Something broke!'; $exceptionFile = '/path/to/MyClass.php'; $exceptionLine = 123;
         $exception = new \RuntimeException($exceptionMessage, 500);
         $reflection = new \ReflectionObject($exception);
         $fileProp = $reflection->getProperty('file'); $fileProp->setValue($exception, $exceptionFile);
         $lineProp = $reflection->getProperty('line'); $lineProp->setValue($exception, $exceptionLine);
         $errorConfig = [ 'type' => 'critical', 'blocking' => 'blocking', 'http_status_code' => 500, 'user_message_key' => 'error-manager::errors.user.generic_error'];
         $this->testConfig['errors'][$errorCode] = $errorConfig;
         $expectedUserMessage = 'Generic exception message.';
         $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);

         // Mock Request JSON
         $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(true);
         // --- CORREZIONE: Rimuovi aspettativa per 'is' ---
         // $this->requestMock->shouldReceive('is')->once()->with('api/*')->andReturn(true); // RIMOSSA
         // --- FINE CORREZIONE ---

         // Mock Translator
         $this->translatorMock->shouldReceive('get')->once()->with($errorConfig['user_message_key'], $context)->andReturn($expectedUserMessage);

         // Mock Handler: Verifica che l'eccezione corretta venga passata
         $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
         $handlerMock->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
         $handlerMock->shouldReceive('handle')
            ->once()
            ->with($errorCode, $errorConfig, $context, $exception); // Verifica tutti gli argomenti
         $manager->registerHandler($handlerMock);

         // Log (minimi, non stiamo testando i log qui)
         $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Handling error/'), Mockery::any())->once();
         $this->loggerMock->shouldReceive('debug')->with('UEM Using translated message', Mockery::any())->once();
         $this->loggerMock->shouldReceive('debug')->with("UEM Handlers dispatched", Mockery::any())->once();
         $this->loggerMock->shouldReceive('info')->with('UEM Returning JSON error response', Mockery::any())->once();


         // --- Act ---
         $response = $manager->handle($errorCode, $context, $exception, false);

         // --- Assert ---
         $this->assertInstanceOf(JsonResponse::class, $response);
         $responseData = $response->getData(true);
         $this->assertEquals($errorCode, $responseData['error']);
         $this->assertEquals($expectedUserMessage, $responseData['message']);
         // Mockery verifica la chiamata all'handler con l'eccezione corretta
    }

    // ========================================================================
    // F. Test Dispatch Handler (`dispatchHandlers` - testato indirettamente via `handle`)
    // ========================================================================
   
    /**
     * 🎯 Test [handle]: Verifica che shouldHandle venga chiamato su tutti gli handler registrati.
     * 🧪 Strategy: Registra mock handler, chiama handle, verifica chiamata a shouldHandle sui mock.
     * @test
     */
    #[Test] // Aggiunto attributo
    public function handle_calls_shouldHandle_on_registered_handlers(): void
    {
        // --- Arrange ---
        $errorCode = 'STATIC_ERROR'; // Un codice errore valido dalla nostra config di test
        $context = ['test_data' => 'value'];

        // Creiamo due mock handler (senza suffissi)
        $handlerMock1 = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock2 = Mockery::mock(ErrorHandlerInterface::class);

        // Mock della Request e modifica config per questo test
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(false);
        $this->requestMock->shouldReceive('is')->once()->with('api/*')->andReturn(false);
        $this->testConfig['errors'][$errorCode]['blocking'] = 'not'; // Assicura risposta null

        // Crea il manager specifico per il test con la config aggiornata
        $manager = new ErrorManager(
            $this->loggerMock,
            $this->translatorMock,
            $this->requestMock,
            $this->testConfig // Usa la config modificata
        );

        // Recupera la config DOPO aver creato il manager (CORREZIONE)
        $errorConfig = $manager->getErrorConfig($errorCode);

        // Impostiamo le aspettative con la config CORRETTA
        $handlerMock1->shouldReceive('shouldHandle')
            ->once()
            ->with($errorConfig) // Usa la config corretta
            ->andReturn(false); // Restituiamo false per semplicità

        $handlerMock2->shouldReceive('shouldHandle')
            ->once()
            ->with($errorConfig) // Usa la config corretta
            ->andReturn(false);

        // Registriamo gli handler nel manager di test
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->times(2);
        $manager->registerHandler($handlerMock1);
        $manager->registerHandler($handlerMock2);

        // Aspettative minime sui log della chiamata handle()
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Dispatched \d+ handlers for/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Handling non-blocking error/'), Mockery::any())->once();

        // --- Act ---
        $response = $manager->handle($errorCode, $context);

        // --- Assert ---
        $this->assertNull($response, "Response should be null for non-blocking HTML requests.");
        // Mockery verifica le chiamate a 'shouldHandle'
    }

    /**
     * 🎯 Test [handle]: Verifica che handle() venga chiamato solo sugli handler applicabili (shouldHandle=true).
     * 🧪 Strategy: Registra due mock handler, uno ritorna true, l'altro false da shouldHandle.
     *             Verifica che handle() venga chiamato solo sul primo.
     * @test
     */
    #[Test] // Aggiunto attributo
    public function handle_calls_handle_on_handlers_where_shouldHandle_is_true(): void
    {
        // --- Arrange ---
        $errorCode = 'STATIC_ERROR';
        $context = ['user_id' => 123];

        // Mock handler senza suffissi (CORREZIONE)
        $handlerShouldHandleTrue = Mockery::mock(ErrorHandlerInterface::class);
        $handlerShouldHandleFalse = Mockery::mock(ErrorHandlerInterface::class);

        // Mock Request e modifica config
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(false);
        $this->requestMock->shouldReceive('is')->once()->with('api/*')->andReturn(false);
        $this->testConfig['errors'][$errorCode]['blocking'] = 'not';

        // Crea il manager specifico per il test
        $manager = new ErrorManager(
            $this->loggerMock,
            $this->translatorMock,
            $this->requestMock,
            $this->testConfig
        );

        // Recupera la config DOPO aver creato il manager
        $errorConfig = $manager->getErrorConfig($errorCode);

        // Aspettative per handler che DEVE gestire
        $handlerShouldHandleTrue->shouldReceive('shouldHandle')
            ->once()
            ->with($errorConfig) // Usa config corretta
            ->andReturn(true);
        $handlerShouldHandleTrue->shouldReceive('handle')
            ->once()
            ->with($errorCode, $errorConfig, $context, null);

        // Aspettative per handler che NON deve gestire
        $handlerShouldHandleFalse->shouldReceive('shouldHandle')
            ->once()
            ->with($errorConfig) // Usa config corretta
            ->andReturn(false);
        $handlerShouldHandleFalse->shouldReceive('handle')->never();

        // Registrazione nel manager di test
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->times(2);
        $manager->registerHandler($handlerShouldHandleTrue);
        $manager->registerHandler($handlerShouldHandleFalse);

        // Log minimi per handle()
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        $this->loggerMock->shouldReceive('debug')->with(Mockery::pattern('/^UEM Dispatching handler:/'), Mockery::on(fn($ctx)=>$ctx['errorCode']===$errorCode))->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Dispatched 1 handlers for/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Handling non-blocking error/'), Mockery::any())->once();

        // --- Act ---
        $response = $manager->handle($errorCode, $context);

        // --- Assert ---
        $this->assertNull($response);
        // Mockery verifica le chiamate a handle()
    }

    /**
     * 🎯 Test [handle]: Verifica esplicitamente che handle() NON venga chiamato se shouldHandle è false.
     * 🧪 Strategy: Registra un solo handler mock che ritorna false da shouldHandle.
     *             Verifica che handle() non venga mai chiamato.
     * @test
     */
    #[Test] // Aggiunto attributo
    public function handle_does_not_call_handle_on_handlers_where_shouldHandle_is_false(): void
    {
        // --- Arrange ---
        $errorCode = 'STATIC_ERROR';
        $context = [];

        // Mock handler senza suffisso
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);

        // Mock Request e modifica config
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(false);
        $this->requestMock->shouldReceive('is')->once()->with('api/*')->andReturn(false);
        $this->testConfig['errors'][$errorCode]['blocking'] = 'not';

        // Crea il manager specifico per il test
        $manager = new ErrorManager(
            $this->loggerMock,
            $this->translatorMock,
            $this->requestMock,
            $this->testConfig
        );

        // Recupera la config DOPO aver creato il manager (CORREZIONE)
        $errorConfig = $manager->getErrorConfig($errorCode);

        // Aspettative: shouldHandle chiamato, handle MAI chiamato
        $handlerMock->shouldReceive('shouldHandle')
            ->once()
            ->with($errorConfig) // Usa config corretta
            ->andReturn(false);
        $handlerMock->shouldReceive('handle')->never();

        // Registrazione nel manager di test
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->once();
        $manager->registerHandler($handlerMock);

        // Log minimi per handle()
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        $this->loggerMock->shouldReceive('debug')->with(Mockery::pattern('/^UEM Dispatching handler:/'), Mockery::any())->never();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Dispatched 0 handlers for/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Handling non-blocking error/'), Mockery::any())->once();

        // --- Act ---
        $response = $manager->handle($errorCode, $context);

        // --- Assert ---
        $this->assertNull($response);
        // Mockery verifica le chiamate (specialmente il ->never())
    }

    /**
     * 🎯 Test [handle]: Verifies dispatch continues if one handler throws an exception.
     * 🧪 Strategy: Register 3 handlers. Second throws exception. Verify third is called
     *             and actively assert that the exception log was called using a flag.
     * @test
     */
    #[Test]
    public function handle_continues_dispatching_if_one_handler_throws_exception(): void
    {
        // --- Arrange ---
        $errorCode = 'ANOTHER_ERROR';
        $context = ['input' => 'value'];
        $exceptionToThrow = new \RuntimeException('Handler 2 simulation failure!');

        $handler1 = Mockery::mock(ErrorHandlerInterface::class);
        $handler2Throws = Mockery::mock(ErrorHandlerInterface::class);
        $handler3 = Mockery::mock(ErrorHandlerInterface::class);

        // Mock Request and config (non-blocking)
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(false);
        $this->requestMock->shouldReceive('is')->once()->with('api/*')->andReturn(false);
        $this->testConfig['errors'][$errorCode]['blocking'] = 'not';

        $manager = new ErrorManager(
            $this->loggerMock,
            $this->translatorMock,
            $this->requestMock,
            $this->testConfig
        );
        $errorConfig = $manager->getErrorConfig($errorCode);

        // --- Flag for Active Verification ---
        $errorLogCalled = false;

        // Handler Expectations
        $handler1->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handler1->shouldReceive('handle')->once()->with($errorCode, $errorConfig, $context, null);

        $handler2Throws->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handler2Throws->shouldReceive('handle')
            ->once()
            ->with($errorCode, $errorConfig, $context, null)
            ->andThrow($exceptionToThrow);

        $handler3->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handler3->shouldReceive('handle')->once()->with($errorCode, $errorConfig, $context, null);

        // Register handlers
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->times(3);
        $manager->registerHandler($handler1);
        $manager->registerHandler($handler2Throws);
        $manager->registerHandler($handler3);

        // --- Log Expectations (focus on the error log verification) ---
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        $this->loggerMock->shouldReceive('debug')->with(Mockery::pattern('/^UEM Dispatching handler:/'), Mockery::any())->times(3);

        // --- CORREZIONE: Usa ->andReturnUsing() per verificare attivamente la chiamata a error ---
        $this->loggerMock->shouldReceive('error')
            ->once()
            ->with(
                Mockery::pattern('/^UEM Exception occurred within handler:/'),
                Mockery::any() // Manteniamo il contesto meno restrittivo per ora
            )
            ->andReturnUsing(function() use (&$errorLogCalled) { // Funzione eseguita quando 'error' viene chiamato
                $errorLogCalled = true; // Imposta il flag
                // Non è necessario restituire nulla qui (void)
            });
        // --- FINE CORREZIONE 'error' ---

        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Dispatched 3 handlers for/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Handling non-blocking error/'), Mockery::any())->once();

        // --- Act ---
        $response = $manager->handle($errorCode, $context);

        // --- Assert ---
        $this->assertNull($response);
        // --- Asserzione Attiva ---
        $this->assertTrue($errorLogCalled, "The logger's error method was expected to be called but wasn't recorded by the flag.");
        // Mockery verificherà anche le altre chiamate (handle H1, handle H3, ecc.)
    }


    /**
     * 🎯 Test [handle]: Verifies that the correct number of dispatched handlers is logged.
     * 🧪 Strategy: Register a mix of applicable and non-applicable handlers. Verify the final log message.
     * @test
     */
    #[Test]
    public function handle_logs_correct_dispatch_count(): void
    {
        // --- Arrange ---
        $errorCode = 'STATIC_ERROR';
        $context = [];
        $dispatchedCount = 2; // Number of handlers expected to return true from shouldHandle
        $registeredCount = 3; // Total number of handlers registered

        // Mock handlers (no suffixes)
        $handlerTrue1 = Mockery::mock(ErrorHandlerInterface::class);
        $handlerTrue2 = Mockery::mock(ErrorHandlerInterface::class);
        $handlerFalse = Mockery::mock(ErrorHandlerInterface::class);

        // Mock Request and config
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(false);
        $this->requestMock->shouldReceive('is')->once()->with('api/*')->andReturn(false);
        $this->testConfig['errors'][$errorCode]['blocking'] = 'not';

        // Create the test-specific manager
        $manager = new ErrorManager(
            $this->loggerMock,
            $this->translatorMock,
            $this->requestMock,
            $this->testConfig
        );
        // Retrieve config AFTER creating the manager
        $errorConfig = $manager->getErrorConfig($errorCode);

        // Handler Expectations
        $handlerTrue1->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerTrue1->shouldReceive('handle')->once();
        $handlerTrue2->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerTrue2->shouldReceive('handle')->once();
        $handlerFalse->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(false);
        $handlerFalse->shouldReceive('handle')->never();

        // Registration
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->times($registeredCount);
        $manager->registerHandler($handlerTrue1);
        $manager->registerHandler($handlerTrue2);
        $manager->registerHandler($handlerFalse);

        // --- Precise Log Expectations ---
        // Initial handling log (OK)
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        // Dispatch log (OK - called for HTrue1 and HTrue2)
        $this->loggerMock->shouldReceive('debug')->with(Mockery::pattern('/^UEM Dispatching handler:/'), Mockery::any())->times($dispatchedCount);
        // KEY final log (Verify message and context)
        $this->loggerMock->shouldReceive('info')
            ->once()
             // The source code increments $dispatchedCount BEFORE calling handle(), so the count is correct here.
            ->with(
                Mockery::pattern("/^UEM Dispatched {$dispatchedCount} handlers for \[$errorCode\]\./"), // Message check
                Mockery::on(function ($ctx) use ($registeredCount) { // Context check
                    return isset($ctx['total_registered']) && $ctx['total_registered'] === $registeredCount;
                })
            );
        // Response log (OK)
         $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Handling non-blocking error/'), Mockery::any())->once();
         // --- End Precise Log Expectations ---


        // --- Act ---
        $response = $manager->handle($errorCode, $context);

        // --- Assert ---
        $this->assertNull($response, "Response should be null for non-blocking HTML request.");
        // Mockery verifies the calls and the specific final log message/context.
    }

        // ========================================================================
    // G. Test Response Building (`buildResponse` - tested indirectly via `handle`)
    // ========================================================================

    /**
     * 🎯 Test [handle]: Returns JsonResponse when request expects JSON.
     * 🧪 Strategy: Mock Request->expectsJson() to return true. Verify response type,
     *             status code, and safe JSON content structure.
     * @test
     */
    #[Test]
    public function handle_returns_jsonResponse_when_request_expects_json(): void
    {
        // --- Arrange ---
        $errorCode = 'STATIC_ERROR';
        $context = ['detail' => 'some data'];
        $expectedStatusCode = 418; // Use a distinct status code for testing
        $expectedUserMessageKey = 'error-manager::errors.user.some_key_for_static'; // Example key
        $translatedUserMessage = 'This is the translated user message for STATIC_ERROR.';

        // Modify config specifically for this test
        $testConfig = $this->testConfig; // Copy base config
        $testConfig['errors'][$errorCode]['http_status_code'] = $expectedStatusCode;
        $testConfig['errors'][$errorCode]['user_message_key'] = $expectedUserMessageKey;
        $testConfig['errors'][$errorCode]['msg_to'] = 'sweet-alert'; // Example display mode
        $testConfig['errors'][$errorCode]['blocking'] = 'semi-blocking'; // Example blocking
        $errorConfig = $testConfig['errors'][$errorCode]; // Get the modified config

        // Create manager with modified config
        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $testConfig);

        // Mock Request: expects JSON
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(true);
        // ->is('api/*') might not be called if expectsJson is true, but mock for safety
        $this->requestMock->shouldReceive('is')->with('api/*')->andReturn(false); // Explicitly false here

        // Mock Translator to return the expected message
        $this->translatorMock->shouldReceive('get')
            ->once()
            ->with($expectedUserMessageKey, $context) // Expect call with key and context
            ->andReturn($translatedUserMessage);

        // Mock Handler (basic interaction)
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')->once()->with($errorCode, $errorConfig, $context, null);
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->once();
        $manager->registerHandler($handlerMock);

        // Mock Logs (minimal)
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        $this->loggerMock->shouldReceive('debug')->with('UEM Using translated message', Mockery::any())->once();
        $this->loggerMock->shouldReceive('debug')->with(Mockery::pattern('/^UEM Dispatching handler:/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Dispatched 1 handlers for/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with('UEM Returning JSON error response', Mockery::any())->once(); // Key log for this scenario

        // --- Act ---
        $response = $manager->handle($errorCode, $context, null, false);

        // --- Assert ---
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($expectedStatusCode, $response->getStatusCode());

        $responseData = $response->getData(true); // Get as associative array
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals($errorCode, $responseData['error']);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals($translatedUserMessage, $responseData['message']); // Check translated user message
        $this->assertArrayHasKey('blocking', $responseData);
        $this->assertEquals($errorConfig['blocking'], $responseData['blocking']);
        $this->assertArrayHasKey('display_mode', $responseData);
        $this->assertEquals($errorConfig['msg_to'], $responseData['display_mode']);
        // Assert that sensitive data is NOT present
        $this->assertArrayNotHasKey('context', $responseData);
        $this->assertArrayNotHasKey('exception', $responseData);
        $this->assertArrayNotHasKey('dev_message', $responseData); // Ensure dev message isn't leaked
    }

        /**
     * 🎯 Test [handle]: Returns JsonResponse when request is API.
     * 🧪 Strategy: Mock Request->expectsJson() false, Request->is('api/*') true.
     *             Verify response type, status, and safe JSON content.
     * @test
     */
    #[Test]
    public function handle_returns_jsonResponse_when_request_is_api(): void
    {
        // --- Arrange ---
        $errorCode = 'STATIC_ERROR';
        $context = ['client_id' => 'api-client-1'];
        // Status code now comes from setUp config modification
        $expectedStatusCode = 400;
        // Expect fallback user message as no key is defined for STATIC_ERROR in setUp
        $expectedUserMessage = __('error-manager::errors.user.fallback_error');

        // Use config from setUp (which now includes http_status_code)
        $errorConfig = $this->testConfig['errors'][$errorCode];
        // Determine the expected blocking level based on fallback logic in prepareErrorInfo
        $expectedBlockingLevel = $errorConfig['blocking'] ?? 'blocking'; // Fallback to 'blocking'
        // Determine expected display mode based on fallback logic in prepareErrorInfo
        $expectedDisplayMode = $errorConfig['msg_to'] ?? $this->testConfig['ui']['default_display_mode'] ?? 'div';


        // Create manager with base config from setUp
        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);

        // Mock Request: NOT expects JSON, IS API
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(false);
        $this->requestMock->shouldReceive('is')->with('api/*')->once()->andReturn(true);

        // Mock Translator (still shouldn't be called for user message)
        $this->translatorMock->shouldNotReceive('get');

        // Mock Handler
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')->once()->with($errorCode, $errorConfig, $context, null);
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->once();
        $manager->registerHandler($handlerMock);

        // Mock Logs
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        $this->loggerMock->shouldReceive('debug')->with(Mockery::pattern('/^UEM Dispatching handler:/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Dispatched 1 handlers for/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with('UEM Returning JSON error response', Mockery::any())->once();

        // --- Act ---
        $response = $manager->handle($errorCode, $context, null, false);

        // --- Assert ---
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals($expectedStatusCode, $response->getStatusCode()); // Should be 400 now

        $responseData = $response->getData(true);
        $this->assertIsArray($responseData);
        $this->assertEquals($errorCode, $responseData['error']);
        $this->assertEquals($expectedUserMessage, $responseData['message']);

        // === CORREZIONE ASSERT ===
        // Verifica il livello di blocking CORRETTO (che sarà il fallback 'blocking')
        $this->assertEquals($expectedBlockingLevel, $responseData['blocking']);
        // Verifica la modalità di display CORRETTA (che sarà il fallback dal config UI)
        $this->assertEquals($expectedDisplayMode, $responseData['display_mode']);
        // === FINE CORREZIONE ASSERT ===

        $this->assertArrayNotHasKey('context', $responseData);
        $this->assertArrayNotHasKey('exception', $responseData);
    }

    /**
     * 🎯 Test [handle]: Throws UltraErrorException for blocking error in HTML context.
     * 🧪 Strategy: Mock Request as HTML, set error config to blocking=true. Expect exception.
     *             Catch exception and verify its properties.
     * @test
     */
    #[Test]
    public function handle_throws_ultraErrorException_for_blocking_error_in_html_context(): void
    {
        // --- Arrange ---
        $errorCode = 'BLOCKING_TEST_HTML';
        $context = ['reason' => 'critical failure'];
        $expectedStatusCode = 503;
        $expectedUserMessageKey = 'error-manager::errors.user.service_unavailable';
        $translatedUserMessage = 'Service temporarily unavailable.';

        // Modify config for this test
        $testConfig = $this->testConfig;
        $testConfig['errors'][$errorCode] = [
            'type' => 'critical',
            'blocking' => 'blocking', // KEY: Ensure it's blocking
            'http_status_code' => $expectedStatusCode,
            'user_message_key' => $expectedUserMessageKey,
            'msg_to' => 'div',
        ];
        $errorConfig = $testConfig['errors'][$errorCode];

        // Create manager with modified config
        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $testConfig);

        // Mock Request: HTML context
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(false);
        $this->requestMock->shouldReceive('is')->with('api/*')->once()->andReturn(false);

        // Mock Translator
        $this->translatorMock->shouldReceive('get')
            ->once()
            ->with($expectedUserMessageKey, $context)
            ->andReturn($translatedUserMessage);

        // Mock Handler (still gets called before the exception is thrown by buildResponse)
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')->once()->with($errorCode, $errorConfig, $context, null);
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->once();
        $manager->registerHandler($handlerMock);

        // Mock Logs
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        $this->loggerMock->shouldReceive('debug')->with('UEM Using translated message', Mockery::any())->once();
        $this->loggerMock->shouldReceive('debug')->with(Mockery::pattern('/^UEM Dispatching handler:/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Dispatched 1 handlers for/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('warning')->with('UEM Throwing exception for blocking error in HTML context.', Mockery::any())->once(); // Key log

        // --- Act & Assert ---
        // Expect the specific exception
        $this->expectException(UltraErrorException::class);
        $this->expectExceptionCode($expectedStatusCode);
        $this->expectExceptionMessage($translatedUserMessage); // Exception message should be user message

        try {
            // This call should trigger the exception inside buildResponse
            $manager->handle($errorCode, $context, null, false); // $throw = false here
        } catch (UltraErrorException $e) {
            // Additionally verify the string code and context within the caught exception
            $this->assertEquals($errorCode, $e->getStringCode());
            $this->assertEquals($context, $e->getContext()); // Context should be passed through
            throw $e; // Re-throw to satisfy PHPUnit's expectException
        }
    }

        /**
     * 🎯 Test [handle]: Returns null for non-blocking error in HTML context.
     * 🧪 Strategy: Mock Request as HTML, ensure error config is non-blocking. Verify null response.
     * @test
     */
    #[Test]
    public function handle_returns_null_for_non_blocking_error_in_html_context(): void
    {
        // --- Arrange ---
        $errorCode = 'ANOTHER_ERROR'; // Config from setUp: type: 'warning', blocking: 'not'
        $context = ['id' => 99];

        // Use the config defined in setUp()
        $errorConfig = $this->testConfig['errors'][$errorCode];
        $this->assertEquals('not', $errorConfig['blocking'], 'Pre-assertion: ANOTHER_ERROR should be non-blocking in test config.');

        // === Create a fresh manager instance specifically for this test ===
        // We re-use the mocks from setUp (logger, translator, request) but a new manager instance
        $manager = new ErrorManager(
            $this->loggerMock,
            $this->translatorMock,
            $this->requestMock,
            $this->testConfig // Use the corrected config from setUp
        );
        // === End fresh manager instance ===

        // Mock Request: HTML context
        $this->requestMock->shouldReceive('expectsJson')->once()->andReturn(false);
        $this->requestMock->shouldReceive('is')->with('api/*')->once()->andReturn(false);

        // Translator fallback expectation is handled in setUp()

        // Mock Handler
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')->once()->with($errorCode, $errorConfig, $context, null);

        // Register handler with the *local* manager instance
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->once();
        $manager->registerHandler($handlerMock);

        // --- Mock Logs - Verify all 3 info calls ---
        // Log 1: Handling start
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        // Log 2: Dispatching handler (debug log)
        $this->loggerMock->shouldReceive('debug')->with(Mockery::pattern('/^UEM Dispatching handler:/'), Mockery::any())->once();
        // Log 3: Dispatch summary
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Dispatched 1 handlers for/'), Mockery::any())->once();
        // Log 4: Non-blocking HTML response log
        $this->loggerMock->shouldReceive('info')->with('UEM Handling non-blocking error for HTML request (flashed to session).', Mockery::any())->once();
        // --- End Mock Logs ---

        // --- Act ---
        $response = $manager->handle($errorCode, $context); // $throw = false by default

        // --- Assert ---
        $this->assertNull($response, "Expected null response for non-blocking HTML request.");
        // Mockery verifies handler calls and logs
    }

    // ========================================================================
    // H. Test Exception Throwing Mode (`$throw = true`)
    // ========================================================================

     /**
 * 🎯 Test [handle]: Throws UltraErrorException when $throw is true, regardless of request type.
 * 🧪 Strategy: Set $throw=true. Mock minimal dependencies.
 *             Verify that UltraErrorException is thrown with correct message and code.
 * @test
 */
#[Test]
public function handle_throws_ultraErrorException_when_throw_is_true(): void
{
    // --- Arrange ---
    $errorCode = 'STATIC_ERROR'; // Config: type:error, http_status:400, no user_message/key
    $context = ['force_throw' => true];
    $expectedStatusCode = 400; // From setUp config for STATIC_ERROR

    // Aspettati il messaggio di fallback reale
    $expectedExceptionMessage = __('error-manager::errors.user.fallback_error');

    $errorConfig = $this->testConfig['errors'][$errorCode];
    $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);

    // Mock Logs
    $this->loggerMock->shouldReceive('info')
        ->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())
        ->once();
    $this->loggerMock->shouldReceive('warning')
        ->with("UEM Throwing UltraErrorException as requested.", Mockery::any())
        ->once();

    // --- Act & Assert ---
    $this->expectException(UltraErrorException::class);
    $this->expectExceptionCode($expectedStatusCode);
    $this->expectExceptionMessage($expectedExceptionMessage);

    $manager->handle($errorCode, $context, null, true); // Call handle with $throw = true
}

        /**
     * 🎯 Test [handle]: Ensures the thrown UltraErrorException contains correct data.
     * 🧪 Strategy: Set $throw=true, pass an original exception. Catch the thrown
     *             UltraErrorException and assert its properties (stringCode, context, status, previous).
     * @test
     */
    #[Test]
    public function handle_thrown_exception_contains_correct_code_context_status_and_previous(): void
    {
        // --- Arrange ---
        $errorCode = 'ANOTHER_ERROR'; // Config: type:warning, blocking:not, no http_status/user_message/key
        $context = ['original_op' => 'test_op'];
        $originalException = new \InvalidArgumentException('Original specific error', 123);
        // Status code è fallback 500 perché non definito per ANOTHER_ERROR
        $expectedStatusCode = 500;

        // --- CORREZIONE: Aspettati il messaggio REALE dal file di lingua per il fallback ---
        $expectedExceptionMessage = __('error-manager::errors.user.fallback_error');
        // --- FINE CORREZIONE ---

        $errorConfig = $this->testConfig['errors'][$errorCode];
        $manager = new ErrorManager($this->loggerMock, $this->translatorMock, $this->requestMock, $this->testConfig);

        // Mock Request (not relevant)
        $this->requestMock->shouldReceive('expectsJson')->never();
        $this->requestMock->shouldReceive('is')->never();

        // Mock Handler
        $handlerMock = Mockery::mock(ErrorHandlerInterface::class);
        $handlerMock->shouldReceive('shouldHandle')->once()->with($errorConfig)->andReturn(true);
        $handlerMock->shouldReceive('handle')->once()->with($errorCode, $errorConfig, $context, $originalException);
        $this->loggerMock->shouldReceive('debug')->with('Registered error handler', Mockery::any())->once();
        $manager->registerHandler($handlerMock);

        // Mock Logs (minimal)
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern("/^UEM Handling error: \[$errorCode\]/"), Mockery::any())->once();
        $this->loggerMock->shouldReceive('debug')->with(Mockery::pattern('/^UEM Dispatching handler:/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('info')->with(Mockery::pattern('/^UEM Dispatched 1 handlers for/'), Mockery::any())->once();
        $this->loggerMock->shouldReceive('warning')->with("UEM Throwing UltraErrorException as requested.", Mockery::any())->once();

        // --- Act & Assert ---
        try {
            $manager->handle($errorCode, $context, $originalException, true);
            $this->fail('Expected UltraErrorException was not thrown.');
        } catch (UltraErrorException $e) {
            $this->assertEquals($errorCode, $e->getStringCode(), "Exception string code mismatch.");
            $this->assertEquals($expectedStatusCode, $e->getCode(), "Exception code (HTTP status) mismatch.");
            // --- CORREZIONE: Verifica il messaggio di fallback reale ---
            $this->assertEquals($expectedExceptionMessage, $e->getMessage(), "Exception message mismatch.");
            // --- FINE CORREZIONE ---
            $this->assertEquals($context, $e->getContext(), "Exception context mismatch.");
            $this->assertSame($originalException, $e->getPrevious(), "Previous exception mismatch.");
        } catch (\Throwable $other) {
             $this->fail('Caught unexpected exception type: ' . get_class($other) . ' - ' . $other->getMessage());
        }
    }
    
    // @todo Aggiungere test per verificare le diverse priorità nella scelta del messaggio (trans > user_message > dev_message > fallback)
    // Questo richiederebbe più mock del translator e variazioni nella config.

} // Fine classe ErrorManagerTest