<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Boots the kernel and creates the SQLite test schema once per PHPUnit process.
 *
 * The schema is created in setUpBeforeClass() so it stays outside the
 * per-test transaction that DAMA DoctrineTestBundle rolls back. To avoid
 * conflicting with DAMA's static-transaction lifecycle, the schema is built
 * on a non-static connection; DAMA then owns the static connection used by
 * the actual tests.
 */
abstract class DatabaseSchemaTestCase extends WebTestCase
{
    private static bool $schemaCreated = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$schemaCreated) {
            return;
        }

        // Create the schema on a connection DAMA does not manage. If the kernel
        // connects through StaticDriver here, StaticDriver begins an outer
        // transaction that conflicts with PHPUnitExtension's beginTransaction()
        // and rollBack() calls for each test.
        $staticDriverClass = \DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver::class;
        $hadStaticConnections = class_exists($staticDriverClass) && $staticDriverClass::isKeepStaticConnections();
        if ($hadStaticConnections) {
            $staticDriverClass::setKeepStaticConnections(false);
        }

        try {
            self::bootKernel();
            $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
            $schemaTool = new SchemaTool($entityManager);
            $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
            self::$schemaCreated = true;
        } finally {
            self::ensureKernelShutdown();
            if ($hadStaticConnections) {
                $staticDriverClass::setKeepStaticConnections(true);
            }
        }
    }

    /**
     * Builds an HTTP client without relying on the "framework.test" config,
     * which this scaffold does not enable.
     */
    protected static function createBrowser(): KernelBrowser
    {
        return new KernelBrowser(self::bootKernel());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $serviceId
     *
     * @return T
     */
    protected static function service(string $serviceId): object
    {
        return self::getContainer()->get($serviceId);
    }
}
