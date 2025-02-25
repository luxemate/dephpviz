<?php

declare(strict_types=1);

namespace DePhpViz\Parser;

use DePhpViz\FileSystem\Model\PhpFile;
use DePhpViz\FileSystem\Contract\FileSystemRepositoryInterface;
use DePhpViz\Parser\Exception\MultipleClassesException;
use DePhpViz\Parser\Exception\ParserException;
use DePhpViz\Parser\Model\ClassDefinition;
use DePhpViz\Parser\Model\Dependency;
use DePhpViz\Parser\NodeVisitor\ClassDefinitionVisitor;
use DePhpViz\Parser\NodeVisitor\DependencyVisitor;
use DePhpViz\Parser\NodeVisitor\NamespaceVisitor;
use PhpParser\Comment\Doc;
use PhpParser\Node\Stmt\Class_;

/**
 * Analyzes PHP files to extract class definitions and dependencies.
 */
class PhpFileAnalyzer
{
    /**
     * @param FileSystemRepositoryInterface $fileSystemRepository The file system repository
     * @param PhpFileParser $phpFileParser The PHP file parser
     */
    public function __construct(
        private readonly FileSystemRepositoryInterface $fileSystemRepository,
        private readonly PhpFileParser $phpFileParser
    ) {
    }

    /**
     * Analyze a PHP file to extract class definition and dependencies.
     *
     * @param PhpFile $phpFile The PHP file to analyze
     * @return array{class: ClassDefinition, dependencies: array<Dependency>}|null
     *         The analysis result or null if no valid class was found
     *
     * @throws ParserException If parsing fails
     * @throws MultipleClassesException If multiple classes are found
     */
    public function analyze(PhpFile $phpFile): ?array
    {
        $filePath = $phpFile->path;
        $code = $this->fileSystemRepository->readFile($filePath);

        // Parse the file
        $ast = $this->phpFileParser->parse($code, $filePath);

        // Analyze namespace
        $namespaceVisitor = new NamespaceVisitor();
        $this->phpFileParser->traverse($ast, [$namespaceVisitor]);
        $namespace = $namespaceVisitor->getNamespaceName();

        // Find class definition
        $classVisitor = new ClassDefinitionVisitor($filePath);
        $this->phpFileParser->traverse($ast, [$classVisitor]);

        // Skip if no class definition was found
        if (!$classVisitor->hasClass()) {
            return null;
        }

        // Extract class information
        $classNode = $classVisitor->getClass();
        assert($classNode instanceof Class_);

        // Handle potential null name
        if ($classNode->name === null) {
            return null; // Skip anonymous classes
        }

        $className = $classNode->name->toString();
        $fullyQualifiedName = $namespace ? $namespace . '\\' . $className : $className;

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

        return [
            'class' => $classDefinition,
            'dependencies' => $dependencies
        ];
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
