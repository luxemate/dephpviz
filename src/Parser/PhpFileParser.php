<?php

declare(strict_types=1);

namespace DePhpViz\Parser;

use DePhpViz\Parser\Exception\ParserException;
use DePhpViz\Parser\Exception\SyntaxErrorException;
use DePhpViz\Parser\Exception\InvalidFileException;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class PhpFileParser
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForNewestSupportedVersion();
    }

    /**
     * Parse PHP code into an Abstract Syntax Tree.
     *
     * @param string $code The PHP code to parse
     * @param string $filePath The file path (for error reporting)
     * @return \PhpParser\Node[] The nodes of the AST
     *
     * @throws ParserException If parsing fails
     */
    public function parse(string $code, string $filePath): array
    {
        // Input validation
        if (trim($code) === '') {
            throw new InvalidFileException(
                sprintf('Empty file or whitespace only: %s', $filePath)
            );
        }

        // Check for PHP opening tag
        if (!str_contains($code, '<?php')) {
            throw new InvalidFileException(
                sprintf('Missing PHP opening tag in file: %s', $filePath)
            );
        }

        try {
            $ast = $this->parser->parse($code);

            if ($ast === null) {
                throw new InvalidFileException(
                    sprintf('Failed to parse file: %s', $filePath)
                );
            }

            return $ast;
        } catch (Error $error) {
            throw new SyntaxErrorException(
                sprintf(
                    'Syntax error in %s (line %d): %s',
                    $filePath,
                    $error->getStartLine(),
                    $error->getMessage()
                ),
                0,
                $error
            );
        } catch (\Exception $e) {
            if ($e instanceof ParserException) {
                throw $e;
            }

            throw new InvalidFileException(
                sprintf('Unexpected error while parsing %s: %s', $filePath, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Traverse the AST with the given visitors.
     *
     * @param array<\PhpParser\Node> $ast The AST to traverse
     * @param array<\PhpParser\NodeVisitor> $visitors The visitors to apply
     * @return array<\PhpParser\Node> The modified AST
     *
     * @throws ParserException If traversal fails
     */
    public function traverse(array $ast, array $visitors): array
    {
        try {
            $traverser = new NodeTraverser();

            foreach ($visitors as $visitor) {
                $traverser->addVisitor($visitor);
            }

            return $traverser->traverse($ast);
        } catch (\Exception $e) {
            throw new InvalidFileException(
                sprintf('Error during AST traversal: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }
}
