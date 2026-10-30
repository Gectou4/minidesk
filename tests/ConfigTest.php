<?php

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private const VARS = ['TEST_VAR', 'TEST_SECRET', 'TEST_SECRET_FILE', 'TEST_FROM_JSON'];

    /** @var string[] */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach (self::VARS as $name) {
            putenv($name);
        }
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    private function tempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'minidesk');
        file_put_contents($path, $content);
        return $this->files[] = $path;
    }

    public function testRequireEnvRetourneLaValeur(): void
    {
        putenv('TEST_VAR=abc');
        $this->assertSame('abc', require_env('TEST_VAR'));
    }

    public function testRequireEnvLeveUneExceptionSiAbsente(): void
    {
        $this->expectException(RuntimeException::class);
        require_env('TEST_VAR');
    }

    public function testRequireEnvLeveUneExceptionSiVide(): void
    {
        putenv('TEST_VAR=');
        $this->expectException(RuntimeException::class);
        require_env('TEST_VAR');
    }

    public function testReadSecretLitLeFichierEtRetireLesEspaces(): void
    {
        putenv('TEST_SECRET_FILE=' . $this->tempFile("s3cret\n"));
        $this->assertSame('s3cret', read_secret('TEST_SECRET'));
    }

    public function testReadSecretFichierPrioritaireSurLaVariable(): void
    {
        putenv('TEST_SECRET=variable');
        putenv('TEST_SECRET_FILE=' . $this->tempFile('fichier'));
        $this->assertSame('fichier', read_secret('TEST_SECRET'));
    }

    public function testReadSecretRepliSurLaVariable(): void
    {
        putenv('TEST_SECRET=variable');
        $this->assertSame('variable', read_secret('TEST_SECRET'));
    }

    public function testReadSecretFichierIllisible(): void
    {
        putenv('TEST_SECRET_FILE=/chemin/inexistant');
        $this->expectException(RuntimeException::class);
        read_secret('TEST_SECRET');
    }

    public function testReadSecretIntrouvable(): void
    {
        $this->expectException(RuntimeException::class);
        read_secret('TEST_SECRET');
    }

    public function testLoadEnvFileDefinitLesVariables(): void
    {
        load_env_file($this->tempFile('{"TEST_FROM_JSON": "valeur"}'));
        $this->assertSame('valeur', getenv('TEST_FROM_JSON'));
    }

    public function testLoadEnvFileNeRemplacePasUneVariableExistante(): void
    {
        putenv('TEST_FROM_JSON=existante');
        load_env_file($this->tempFile('{"TEST_FROM_JSON": "valeur"}'));
        $this->assertSame('existante', getenv('TEST_FROM_JSON'));
    }

    public function testLoadEnvFileIgnoreUnFichierAbsent(): void
    {
        load_env_file('/chemin/inexistant.json');
        $this->assertFalse(getenv('TEST_FROM_JSON'));
    }

    public function testLoadEnvFileIgnoreUnJsonInvalide(): void
    {
        $previous = ini_set('error_log', $this->tempFile(''));
        load_env_file($this->tempFile('pas du json'));
        ini_set('error_log', (string) $previous);
        $this->assertFalse(getenv('TEST_FROM_JSON'));
    }
}
