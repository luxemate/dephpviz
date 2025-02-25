<?php

declare(strict_types=1);

namespace DePhpViz\Parser;

use DePhpViz\Parser\Exception\ParserException;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Service for parsing PHP files using PHP-Parser.
 */
class PhpFileParser
{
    private Parser $parser;

    public function __construct()
    {
        // Updated to use the current PHP-Parser API
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
        try {
            $ast = $this->parser->parse($code);

            if ($ast === null) {
                throw new ParserException(
                    sprintf('Failed to parse file: %s', $filePath)
                );
            }

            return $ast;
        } catch (Error $error) {
            throw new ParserException(
                sprintf('Parse error in %s: %s', $filePath, $error->getMessage()),
                0,
                $error
            );
        }
    }

    /**
     * Traverse the AST with the given visitors.
     *
     * @param array<\PhpParser\Node> $ast The AST to traverse
     * @param array<\PhpParser\NodeVisitor> $visitors The visitors to apply
     * @return array<\PhpParser\Node> The modified AST
     */
    public function traverse(array $ast, array $visitors): array
    {
        $traverser = new NodeTraverser();

        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        return $traverser->traverse($ast);
    }
}
