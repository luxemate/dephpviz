<?php

declare(strict_types=1);

namespace DePhpViz\Parser;

use DePhpViz\FileSystem\Model\PhpFile;
use DePhpViz\FileSystem\Contract\FileSystemRepositoryInterface;
use DePhpViz\Parser\Exception\MissingNamespaceException;
use DePhpViz\Parser\Exception\MultipleDefinitionsException;
use DePhpViz\Parser\Exception\ParserException;
use DePhpViz\Parser\Exception\UnknownDefinitionException;
use DePhpViz\Parser\Model\AbstractDefinition;
use DePhpViz\Parser\Model\ClassDefinition;
use DePhpViz\Parser\Model\Dependency;
use DePhpViz\Parser\Model\InterfaceDefinition;
use DePhpViz\Parser\Model\TraitDefinition;
use DePhpViz\Parser\NodeVisitor\DefinitionVisitor;
use DePhpViz\Parser\NodeVisitor\DependencyVisitor;
use DePhpViz\Parser\NodeVisitor\NamespaceVisitor;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
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
     * Analyze a PHP file to extract definition and dependencies.
     *
     * @param PhpFile $phpFile The PHP file to analyze
     * @param bool $requireNamespace Whether to require a namespace (default: true)
     * @return array{definition: AbstractDefinition, dependencies: array<Dependency>}|null
     *         The analysis result or null if no valid definition was found
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

            // Find definition (class, trait, or interface)
            $definitionVisitor = new DefinitionVisitor($filePath);
            $this->phpFileParser->traverse($ast, [$definitionVisitor]);

            // Skip if no definition was found
            if (!$definitionVisitor->hasDefinition()) {
                $this->logger->info(sprintf('No definition found in %s', $phpFile->relativePath));
                return null;
            }

            // Get definition information
            $definitionNode = $definitionVisitor->getDefinition();
            $definitionType = $definitionVisitor->getDefinitionType();

            // Handle potentially null name for anonymous classes
            if ($definitionNode instanceof Class_ && $definitionNode->name === null) {
                $this->errorCollector->addError(
                    $phpFile,
                    'Anonymous class found, skipping',
                    'anonymous_class'
                );
                return null;
            }

            // Get the name from the appropriate node type
            $name = match (true) {
                $definitionNode instanceof Class_,
                $definitionNode instanceof Interface_,
                $definitionNode instanceof Trait_ => $definitionNode->name?->toString() ?? '',
                default => throw new UnknownDefinitionException('Unknown definition type')
            };

            $fullyQualifiedName = $namespace ? $namespace . '\\' . $name : $name;

            // Extract doc comment
            $docComment = [];
            if ($definitionNode->getDocComment() instanceof Doc) {
                $docComment = $this->parseDocComment($definitionNode->getDocComment()->getText());
            }

            // Create appropriate definition object based on type
            $definition = match ($definitionType) {
                'trait' => new TraitDefinition(
                    $name,
                    $namespace,
                    $fullyQualifiedName,
                    $filePath,
                    $docComment
                ),
                'interface' => new InterfaceDefinition(
                    $name,
                    $namespace,
                    $fullyQualifiedName,
                    $filePath,
                    $docComment
                ),
                default => new ClassDefinition(
                    $name,
                    $namespace,
                    $fullyQualifiedName,
                    $filePath,
                    $docComment,
                    $definitionNode instanceof Class_ ? $definitionNode->isAbstract() : false,
                    $definitionNode instanceof Class_ ? $definitionNode->isFinal() : false
                )
            };

            $this->logger->info(sprintf(
                'Found %s %s in %s',
                $definitionType,
                $fullyQualifiedName,
                $phpFile->relativePath
            ));

            // Extract namespace dependencies
            $dependencyVisitor = new DependencyVisitor($fullyQualifiedName);
            $this->phpFileParser->traverse($ast, [$dependencyVisitor]);
            $dependencies = $dependencyVisitor->getDependencies();

//            // Extract trait usage if this is a class
//            if ($definitionType === 'class') {
//                $traitUsageVisitor = new TraitUsageVisitor($fullyQualifiedName);
//                $this->phpFileParser->traverse($ast, [$traitUsageVisitor]);
//                $traitDependencies = $traitUsageVisitor->getUsedTraits();
//                $dependencies = array_merge($dependencies, $traitDependencies);
//            }

            $this->logger->info(sprintf(
                'Found %d dependencies for %s',
                count($dependencies),
                $fullyQualifiedName
            ));

            return [
                'definition' => $definition,
                'dependencies' => $dependencies
            ];

        } catch (MultipleDefinitionsException $e) {
            $this->errorCollector->addException($phpFile, $e, 'multiple_definitions');
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
