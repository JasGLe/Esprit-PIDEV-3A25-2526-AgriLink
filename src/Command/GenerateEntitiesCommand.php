<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:generate:entities',
    description: 'Generate Doctrine entities from existing database tables (reverse engineering)',
)]
class GenerateEntitiesCommand extends Command
{
    private array $typeMapping = [
        'integer' => 'int',
        'smallint' => 'int',
        'bigint' => 'int',
        'decimal' => 'string',
        'float' => 'float',
        'double' => 'float',
        'string' => 'string',
        'text' => 'string',
        'boolean' => 'bool',
        'datetime' => '\DateTimeInterface',
        'datetime_immutable' => '\DateTimeImmutable',
        'date' => '\DateTimeInterface',
        'date_immutable' => '\DateTimeImmutable',
        'time' => '\DateTimeInterface',
        'time_immutable' => '\DateTimeImmutable',
        'json' => 'array',
        'array' => 'array',
        'simple_array' => 'array',
        'blob' => 'string',
        'binary' => 'string',
        'guid' => 'string',
    ];

    private array $doctrineTypeMapping = [
        'int' => 'Types::INTEGER',
        'smallint' => 'Types::SMALLINT',
        'bigint' => 'Types::BIGINT',
        'decimal' => 'Types::DECIMAL',
        'float' => 'Types::FLOAT',
        'double' => 'Types::FLOAT',
        'varchar' => 'Types::STRING',
        'char' => 'Types::STRING',
        'text' => 'Types::TEXT',
        'longtext' => 'Types::TEXT',
        'mediumtext' => 'Types::TEXT',
        'tinytext' => 'Types::TEXT',
        'boolean' => 'Types::BOOLEAN',
        'tinyint' => 'Types::BOOLEAN',
        'datetime' => 'Types::DATETIME_MUTABLE',
        'timestamp' => 'Types::DATETIME_MUTABLE',
        'date' => 'Types::DATE_MUTABLE',
        'time' => 'Types::TIME_MUTABLE',
        'json' => 'Types::JSON',
        'blob' => 'Types::BLOB',
        'binary' => 'Types::BINARY',
        'enum' => 'Types::STRING',
    ];

    private array $foreignKeys = [];
    private array $allTables = [];

    public function __construct(
        private Connection $connection,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('backup', 'b', InputOption::VALUE_NONE, 'Backup existing entities before generating new ones')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be generated without writing files')
            ->addOption('table', 't', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Generate only specific tables', []);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();
        $dryRun = $input->getOption('dry-run');
        $backup = $input->getOption('backup');
        $specificTables = $input->getOption('table');

        $io->title('Database Reverse Engineering - Entity Generator');

        $entityDir = $this->projectDir . '/src/Entity';
        $repositoryDir = $this->projectDir . '/src/Repository';
        $backupDir = $this->projectDir . '/src/Entity_backup_' . date('Y-m-d_H-i-s');

        // Backup existing entities if requested
        if ($backup && !$dryRun && is_dir($entityDir)) {
            $io->section('Backing up existing entities');
            $filesystem->mirror($entityDir, $backupDir);
            $io->success("Entities backed up to: $backupDir");
        }

        // Get all tables
        $schemaManager = $this->connection->createSchemaManager();
        $tables = $schemaManager->listTables();
        $this->allTables = array_map(fn($t) => $t->getName(), $tables);

        // Filter tables if specific ones requested
        if (!empty($specificTables)) {
            $tables = array_filter($tables, fn($t) => in_array($t->getName(), $specificTables));
        }

        // Load foreign keys
        $this->loadForeignKeys();

        $io->section('Generating entities from ' . count($tables) . ' tables');

        $generatedEntities = [];
        $generatedRepositories = [];

        foreach ($tables as $table) {
            $tableName = $table->getName();
            $className = $this->tableToClassName($tableName);

            $io->writeln("  Processing table: <info>$tableName</info> -> <comment>$className</comment>");

            // Generate entity
            $entityCode = $this->generateEntityCode($table, $className);
            $entityPath = "$entityDir/$className.php";

            if ($dryRun) {
                $io->writeln("    Would create: $entityPath");
            } else {
                $filesystem->dumpFile($entityPath, $entityCode);
                $generatedEntities[] = $className;
            }

            // Generate repository
            $repositoryCode = $this->generateRepositoryCode($className);
            $repositoryPath = "$repositoryDir/{$className}Repository.php";

            if ($dryRun) {
                $io->writeln("    Would create: $repositoryPath");
            } else {
                $filesystem->dumpFile($repositoryPath, $repositoryCode);
                $generatedRepositories[] = "{$className}Repository";
            }
        }

        if ($dryRun) {
            $io->warning('Dry run completed - no files were written');
        } else {
            $io->success([
                'Generated ' . count($generatedEntities) . ' entities',
                'Generated ' . count($generatedRepositories) . ' repositories',
            ]);

            $io->section('Generated Entities');
            $io->listing($generatedEntities);

            $io->note([
                'Next steps:',
                '1. Review the generated entities in src/Entity/',
                '2. Run: php bin/console doctrine:schema:validate',
                '3. If needed, run: php bin/console doctrine:migrations:diff',
            ]);
        }

        return Command::SUCCESS;
    }

    private function loadForeignKeys(): void
    {
        $sql = "
            SELECT 
                TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ";

        $result = $this->connection->executeQuery($sql);
        while ($row = $result->fetchAssociative()) {
            $tableName = $row['TABLE_NAME'];
            $columnName = $row['COLUMN_NAME'];
            $this->foreignKeys[$tableName][$columnName] = [
                'table' => $row['REFERENCED_TABLE_NAME'],
                'column' => $row['REFERENCED_COLUMN_NAME'],
            ];
        }
    }

    private function tableToClassName(string $tableName): string
    {
        // Handle special cases
        $specialCases = [
            'SecurityEvent' => 'SecurityEvent',
            'User' => 'User',
            'UserSession' => 'UserSession',
        ];

        if (isset($specialCases[$tableName])) {
            return $specialCases[$tableName];
        }

        // Convert snake_case to PascalCase
        $className = str_replace('_', ' ', $tableName);
        $className = ucwords($className);
        $className = str_replace(' ', '', $className);

        return $className;
    }

    private function columnToPropertyName(string $columnName): string
    {
        // Keep camelCase columns as-is
        if (preg_match('/[A-Z]/', $columnName)) {
            return lcfirst($columnName);
        }

        // Convert snake_case to camelCase
        $propertyName = str_replace('_', ' ', $columnName);
        $propertyName = ucwords($propertyName);
        $propertyName = str_replace(' ', '', $propertyName);

        return lcfirst($propertyName);
    }

    private function generateEntityCode($table, string $className): string
    {
        $tableName = $table->getName();
        $columns = $table->getColumns();
        $primaryKey = $table->getPrimaryKey();
        $primaryKeyColumns = $primaryKey ? $primaryKey->getColumns() : ['id'];

        $properties = [];
        $methods = [];
        $uses = ['Doctrine\ORM\Mapping as ORM'];
        $hasCollections = false;

        // Check if this table is referenced by others (for OneToMany relations)
        $referencedBy = $this->getReferencedBy($tableName);

        foreach ($columns as $column) {
            $columnName = $column->getName();
            $propertyName = $this->columnToPropertyName($columnName);
            $isPrimaryKey = in_array($columnName, $primaryKeyColumns);
            $isNullable = !$column->getNotnull();
            $hasDefault = $column->getDefault() !== null;

            // Check if this column is a foreign key
            $foreignKey = $this->foreignKeys[$tableName][$columnName] ?? null;

            if ($foreignKey) {
                // ManyToOne relation
                $relatedClass = $this->tableToClassName($foreignKey['table']);
                $properties[] = $this->generateRelationProperty($propertyName, $relatedClass, $columnName, $isNullable);
                $methods[] = $this->generateRelationGetterSetter($propertyName, $relatedClass, $isNullable);
            } else {
                // Regular column
                $phpType = $this->getPhpType($column);
                $doctrineType = $this->getDoctrineType($column);
                $columnOptions = $this->getColumnOptions($column, $isPrimaryKey);

                $properties[] = $this->generateProperty($propertyName, $columnName, $phpType, $doctrineType, $isPrimaryKey, $isNullable, $hasDefault, $column, $columnOptions);
                $methods[] = $this->generateGetterSetter($propertyName, $phpType, $isNullable, $isPrimaryKey);
            }
        }

        // Add OneToMany relations
        foreach ($referencedBy as $ref) {
            $hasCollections = true;
            $relatedClass = $this->tableToClassName($ref['table']);
            $collectionName = lcfirst($relatedClass) . 's';
            $mappedBy = $this->columnToPropertyName($ref['column']);

            $properties[] = $this->generateOneToManyProperty($collectionName, $relatedClass, $mappedBy);
            $methods[] = $this->generateCollectionGetterAdderRemover($collectionName, $relatedClass);
        }

        if ($hasCollections) {
            $uses[] = 'Doctrine\Common\Collections\ArrayCollection';
            $uses[] = 'Doctrine\Common\Collections\Collection';
        }
        $uses[] = 'Doctrine\DBAL\Types\Types';

        sort($uses);

        // Build constructor if needed
        $constructor = '';
        if ($hasCollections) {
            $constructorBody = '';
            foreach ($referencedBy as $ref) {
                $relatedClass = $this->tableToClassName($ref['table']);
                $collectionName = lcfirst($relatedClass) . 's';
                $constructorBody .= "        \$this->$collectionName = new ArrayCollection();\n";
            }
            $constructor = "\n    public function __construct()\n    {\n$constructorBody    }\n";
        }

        // Build the entity class
        $useStatements = implode("\n", array_map(fn($u) => "use $u;", $uses));
        $propertiesCode = implode("\n\n", $properties);
        $methodsCode = implode("\n\n", $methods);

        return <<<PHP
<?php

namespace App\Entity;

$useStatements

#[ORM\Entity(repositoryClass: \\App\\Repository\\{$className}Repository::class)]
#[ORM\Table(name: '$tableName')]
class $className
{
$propertiesCode
$constructor
$methodsCode
}
PHP;
    }

    private function getReferencedBy(string $tableName): array
    {
        $references = [];
        foreach ($this->foreignKeys as $table => $columns) {
            foreach ($columns as $column => $fk) {
                if (strtolower($fk['table']) === strtolower($tableName)) {
                    $references[] = [
                        'table' => $table,
                        'column' => $column,
                    ];
                }
            }
        }
        return $references;
    }

    private function getPhpType($column): string
    {
        $type = $column->getType()->getName();

        // Handle enum types
        if (str_contains(strtolower($column->getType()->getName()), 'enum')) {
            return 'string';
        }

        return $this->typeMapping[$type] ?? 'mixed';
    }

    private function getDoctrineType($column): string
    {
        $platformType = strtolower($column->getType()->getName());

        // Check column comment or platform options for enum
        $columnDefinition = $column->toArray();

        if (str_contains($platformType, 'enum')) {
            return 'Types::STRING';
        }

        // Map common MySQL types
        foreach ($this->doctrineTypeMapping as $mysqlType => $doctrineType) {
            if (str_contains($platformType, $mysqlType)) {
                return $doctrineType;
            }
        }

        return 'Types::STRING';
    }

    private function getColumnOptions($column, bool $isPrimaryKey): array
    {
        $options = [];

        if ($column->getLength() && !$isPrimaryKey) {
            $options['length'] = $column->getLength();
        }

        if ($column->getPrecision() && $column->getScale()) {
            $options['precision'] = $column->getPrecision();
            $options['scale'] = $column->getScale();
        }

        return $options;
    }

    private function generateProperty(string $propertyName, string $columnName, string $phpType, string $doctrineType, bool $isPrimaryKey, bool $isNullable, bool $hasDefault, $column, array $options): string
    {
        $attributes = [];

        if ($isPrimaryKey) {
            $attributes[] = '#[ORM\Id]';
            $attributes[] = '#[ORM\GeneratedValue]';
            $attributes[] = "#[ORM\Column(type: Types::INTEGER)]";
        } else {
            $columnAttrs = ["type: $doctrineType"];

            if ($columnName !== $propertyName && $columnName !== $this->propertyToColumnName($propertyName)) {
                $columnAttrs[] = "name: '$columnName'";
            }

            if (isset($options['length']) && $options['length'] != 255) {
                $columnAttrs[] = "length: {$options['length']}";
            }

            if (isset($options['precision'])) {
                $columnAttrs[] = "precision: {$options['precision']}";
                $columnAttrs[] = "scale: {$options['scale']}";
            }

            if ($isNullable) {
                $columnAttrs[] = 'nullable: true';
            }

            $attributes[] = '#[ORM\Column(' . implode(', ', $columnAttrs) . ')]';
        }

        $attributesStr = implode("\n    ", $attributes);
        $nullableType = $isNullable && !$isPrimaryKey ? '?' : '';
        $defaultValue = '';

        if ($isPrimaryKey) {
            $defaultValue = '';
        } elseif ($hasDefault || $isNullable) {
            $defaultValue = ' = null';
        }

        return "    $attributesStr\n    private $nullableType$phpType \$$propertyName$defaultValue;";
    }

    private function propertyToColumnName(string $propertyName): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $propertyName));
    }

    private function generateRelationProperty(string $propertyName, string $relatedClass, string $columnName, bool $isNullable): string
    {
        $nullable = $isNullable ? ', nullable: true' : '';
        $nullableType = $isNullable ? '?' : '';

        return <<<PHP
    #[ORM\ManyToOne(targetEntity: $relatedClass::class, inversedBy: '{$this->getInversedByName($relatedClass)}')]
    #[ORM\JoinColumn(name: '$columnName'$nullable)]
    private $nullableType$relatedClass \$$propertyName = null;
PHP;
    }

    private function getInversedByName(string $className): string
    {
        return lcfirst($className) . 's';
    }

    private function generateOneToManyProperty(string $propertyName, string $relatedClass, string $mappedBy): string
    {
        return <<<PHP
    #[ORM\OneToMany(targetEntity: $relatedClass::class, mappedBy: '$mappedBy')]
    private Collection \$$propertyName;
PHP;
    }

    private function generateGetterSetter(string $propertyName, string $phpType, bool $isNullable, bool $isPrimaryKey): string
    {
        $methodName = ucfirst($propertyName);
        $nullableReturn = $isNullable && !$isPrimaryKey ? '?' : '';
        $nullableParam = $isNullable ? '?' : '';

        $getter = <<<PHP
    public function get$methodName(): $nullableReturn$phpType
    {
        return \$this->$propertyName;
    }
PHP;

        if ($isPrimaryKey) {
            return $getter;
        }

        $setter = <<<PHP

    public function set$methodName($nullableParam$phpType \$$propertyName): static
    {
        \$this->$propertyName = \$$propertyName;

        return \$this;
    }
PHP;

        return $getter . "\n" . $setter;
    }

    private function generateRelationGetterSetter(string $propertyName, string $relatedClass, bool $isNullable): string
    {
        $methodName = ucfirst($propertyName);
        $nullableReturn = $isNullable ? '?' : '';
        $nullableParam = $isNullable ? '?' : '';

        return <<<PHP
    public function get$methodName(): $nullableReturn$relatedClass
    {
        return \$this->$propertyName;
    }

    public function set$methodName($nullableParam$relatedClass \$$propertyName): static
    {
        \$this->$propertyName = \$$propertyName;

        return \$this;
    }
PHP;
    }

    private function generateCollectionGetterAdderRemover(string $propertyName, string $relatedClass): string
    {
        $methodName = ucfirst($propertyName);
        $singularName = rtrim($propertyName, 's');
        $singularMethod = ucfirst($singularName);

        return <<<PHP
    /**
     * @return Collection<int, $relatedClass>
     */
    public function get$methodName(): Collection
    {
        return \$this->$propertyName;
    }

    public function add$singularMethod($relatedClass \$$singularName): static
    {
        if (!\$this->{$propertyName}->contains(\$$singularName)) {
            \$this->{$propertyName}->add(\$$singularName);
        }

        return \$this;
    }

    public function remove$singularMethod($relatedClass \$$singularName): static
    {
        \$this->{$propertyName}->removeElement(\$$singularName);

        return \$this;
    }
PHP;
    }

    private function generateRepositoryCode(string $className): string
    {
        return <<<PHP
<?php

namespace App\Repository;

use App\Entity\\$className;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<$className>
 *
 * @method $className|null find(\$id, \$lockMode = null, \$lockVersion = null)
 * @method $className|null findOneBy(array \$criteria, array \$orderBy = null)
 * @method {$className}[]    findAll()
 * @method {$className}[]    findBy(array \$criteria, array \$orderBy = null, \$limit = null, \$offset = null)
 */
class {$className}Repository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry \$registry)
    {
        parent::__construct(\$registry, {$className}::class);
    }

    public function save($className \$entity, bool \$flush = false): void
    {
        \$this->getEntityManager()->persist(\$entity);

        if (\$flush) {
            \$this->getEntityManager()->flush();
        }
    }

    public function remove($className \$entity, bool \$flush = false): void
    {
        \$this->getEntityManager()->remove(\$entity);

        if (\$flush) {
            \$this->getEntityManager()->flush();
        }
    }
}
PHP;
    }
}
