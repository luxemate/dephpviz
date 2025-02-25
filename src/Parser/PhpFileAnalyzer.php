<?php

declare(strict_types=1);

namespace DePhpViz\Parser;

use DePhpViz\FileSystem\Model\PhpFile;
use DePhpViz\FileSystem\Contract\FileSystemRepositoryInterface;
use DePhpViz\Parser\Exception\MissingNamespaceException;
use DePhpViz\Parser\Exception\MultipleClassesException;
use DePhpViz\Parser\Exception\ParserException;
use DePhpViz\Parser\Model\ClassDefinition;
use DePhpViz\Parser\Model\Dependency;
use DePhpViz\Parser\NodeVisitor\ClassDefinitionVisitor;
use DePhpViz\Parser\NodeVisitor\DependencyVisitor;
use DePhpViz\Parser\NodeVisitor\NamespaceVisitor;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Class_;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Analyzes PHP files to extract class definitions and dependencies.
 */
class PhpFileAnalyzer
{
    /**
     * @param FileSystemRepositoryInterface $fileSystemRepository The file system repository
     * @param PhpFileParser $phpFileParser The PHP file parser
     * @param ErrorCollector $errorCollector Error collector service
     * @param LoggerInterface $logger Logger for recording analysis information
     */
    public function __construct(
        private readonly FileSystemRepositoryInterface $fileSystemRepository,
        private readonly PhpFileParser $phpFileParser,
        private readonly ErrorCollector $errorCollector,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Analyze a PHP file to extract class definition and dependencies.
     *
     * @param PhpFile $phpFile The PHP file to analyze
     * @param bool $requireNamespace Whether to require a namespace (default: true)
     * @return array{class: ClassDefinition, dependencies: array<Dependency>}|null
     *         The analysis result or null if no valid class was found
     */
    public function analyze(PhpFile $phpFile, bool $requireNamespace = true): ?array
    {
        try {
            $filePath = $phpFile->path;
            $code = $this->fileSystemRepository->readFile($filePath);

            // Parse the file
            $ast = $this->phpFileParser->parse($code, $filePath);

            // Analyze namespace
            $namespaceVisitor = new NamespaceVisitor();
            $this->phpFileParser->traverse($ast, [$namespaceVisitor]);
            $namespace = $namespaceVisitor->getNamespaceName();

            // Validate namespace if required
            if ($requireNamespace && !$namespaceVisitor->hasNamespace()) {
                throw new MissingNamespaceException(
                    sprintf('No namespace defined in %s', $phpFile->relativePath)
                );
            }

            // Find class definition
            $classVisitor = new ClassDefinitionVisitor($filePath);
            $this->phpFileParser->traverse($ast, [$classVisitor]);

            // Skip if no class definition was found
            if (!$classVisitor->hasClass()) {
                $this->logger->info(sprintf('No class definition found in %s', $phpFile->relativePath));
                return null;
            }

            // Extract class information
            $classNode = $classVisitor->getClass();
            assert($classNode instanceof Class_);

            // Handle potential null name (anonymous classes)
            if ($classNode->name === null) {
                $this->errorCollector->addError(
                    $phpFile,
                    'Anonymous class found, skipping',
                    'anonymous_class'
                );
                return null;
            }

            $className = $classNode->name->toString();
            $fullyQualifiedName = $namespace ? $namespace . '\\' . $className : $className;

            $this->logger->info(sprintf(
                'Found class %s in %s',
                $fullyQualifiedName,
                $phpFile->relativePath
            ));

            // Extract doc comment
            $docComment = [];
            if ($classNode->getDocComment() instanceof Doc) {
                $docComment = $this->parseDocComment($classNode->getDocComment()->getText());
            }

            // Create class definition
            $classDefinition = new ClassDefinition(
                $className,
                $namespace,
                $fullyQualifiedName,
                $filePath,
                $docComment,
                $classNode->isAbstract(),
                $classNode->isFinal()
            );

            // Extract dependencies
            $dependencyVisitor = new DependencyVisitor($fullyQualifiedName);
            $this->phpFileParser->traverse($ast, [$dependencyVisitor]);
            $dependencies = $dependencyVisitor->getDependencies();

            $this->logger->info(sprintf(
                'Found %d dependencies for %s',
                count($dependencies),
                $fullyQualifiedName
            ));

            return [
                'class' => $classDefinition,
                'dependencies' => $dependencies
            ];

        } catch (MultipleClassesException $e) {
            $this->errorCollector->addException($phpFile, $e, 'multiple_classes');
            return null;
        } catch (MissingNamespaceException $e) {
            $this->errorCollector->addException($phpFile, $e, 'missing_namespace');
            return null;
        } catch (ParserException $e) {
            $this->errorCollector->addException($phpFile, $e, 'parser_error');
            return null;
        } catch (\Exception $e) {
            $this->errorCollector->addException($phpFile, $e, 'unexpected_error');
            $this->logger->error(sprintf(
                'Unexpected error processing %s: %s',
                $phpFile->relativePath,
                $e->getMessage()
            ), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Parse a doc comment to extract relevant information.
     *
     * @param string $docComment The doc comment text
     * @return array<string> The parsed doc comment lines
     */
    private function parseDocComment(string $docComment): array
    {
        $lines = explode("\n", $docComment);
        $result = [];

        foreach ($lines as $line) {
            // Remove comment characters and trim whitespace
            $line = trim(str_replace(['/**', '*/', '*'], '', $line));

            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }
}
