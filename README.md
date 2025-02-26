# DePhpViz

**DePhpViz** is a sophisticated PHP dependency visualization tool that analyzes PHP codebases to extract and visualize class relationships. It maps the dependencies between classes, interfaces, and traits, providing an interactive graph visualization to help developers understand complex codebases.

This was an attempt to use [Anthropic Claude 3.7](http://claude.ai/) for development of the entire project from just the idea.

## Key Features

- **Comprehensive PHP Analysis**: Scans and analyzes PHP files to extract classes, interfaces, and traits
- **Dependency Mapping**: Identifies relationships through `use` statements, inheritance (`extends`), interface implementation (`implements`), and trait usage
- **Interactive Graph Visualization**: Provides a web-based interface to explore the dependency graph
- **Search Functionality**: Quickly locate specific classes in large codebases
- **Dependency Highlighting**: Select a node to highlight its dependencies and dependents in different colors
- **Performance Optimized**: Capable of handling large codebases (10,000+ files)
- **Detailed Reporting**: Generates error reports and validation statistics
- **Cross-Platform**: Works on Windows, macOS, and Linux

## Requirements

- PHP 8.3 or higher
- Composer
- Node.js and npm/yarn (for frontend development)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/dephpviz.git
   cd dephpviz
   ```

2. Install dependencies:
   ```bash
   composer install
   yarn install  # or npm install
   ```

3. Build the frontend assets:
   ```bash
   yarn build  # or npm run build
   ```

## Usage

### Analyzing a Codebase

To analyze a PHP project and generate a dependency graph:

```bash
bin/dephpviz analyze /path/to/your/php/project
```

This command will:
1. Scan the specified directory for PHP files
2. Analyze each file for class definitions and dependencies
3. Build a dependency graph
4. Serialize the graph to JSON
5. Offer to start a web server for visualization

### Command Options

```bash
# Specify an output file for the graph data
bin/dephpviz analyze /path/to/project --output=/path/to/output.json

# Require namespace for all PHP files
bin/dephpviz analyze /path/to/project --require-namespace

# Generate an error report
bin/dephpviz analyze /path/to/project --error-report=/path/to/report.json

# Perform additional graph validation
bin/dephpviz analyze /path/to/project --validate
```

### Starting the Visualization Server

You can also start the visualization server directly:

```bash
bin/dephpviz server --graph-data=/path/to/graph.json
```

Then open your browser at http://127.0.0.1:8080 to view the visualization.

### Testing with Sample Data

To generate and visualize sample data for testing:

```bash
bin/dephpviz prototype --nodes=50 --connectivity=0.3
```

## Visualization Features

![DePhpViz UI Features](screenshots/ui-features.png)

The web interface provides several interactive features:

- **Zooming and Panning**: Navigate large graphs with mouse and keyboard controls
- **Node Selection**: Click on a node to view its details and highlight its connections
- **Search**: Find specific classes, interfaces, or traits by name
- **Dependency Highlighting**: 
  - Blue: Classes/interfaces this node depends on
  - Green: Classes/interfaces that depend on this node
- **Node Types**:
  - Blue circles: Classes
  - Orange diamonds: Interfaces
  - Green rectangles: Traits

## Screenshots

### Graph Visualization
![Graph Visualization](https://github.com/user-attachments/assets/54c51b2c-4c7e-4af7-850a-c73cc06b49d1)

### Dependency Highlighting
![Dependency Highlighting](https://github.com/user-attachments/assets/22969d22-b377-429a-b190-3d4e6cd75e97)

## Architecture

DePhpViz is built with a clean, modular architecture:

- **Parser**: Uses PHP-Parser to analyze PHP files and extract class information
- **Graph Builder**: Constructs a dependency graph from the parsed data
- **Web Server**: Provides API endpoints and serves the visualization interface
- **Frontend**: Vue.js application with Cytoscape.js for graph visualization

## Technologies

- **PHP Components**:
  - [PHP-Parser](https://github.com/nikic/PHP-Parser): For parsing PHP code
  - [graphp/graph](https://github.com/graphp/graph): For graph data structures
  - [Symfony Components](https://symfony.com/components): Console, Filesystem, Process, HTTP Foundation, etc.
  - [Monolog](https://github.com/Seldaek/monolog): For logging

- **Frontend**:
  - [Vue.js](https://vuejs.org/): For building the UI
  - [Cytoscape.js](https://js.cytoscape.org/): For graph visualization
  - [Axios](https://github.com/axios/axios): For API requests
  - [Lodash](https://lodash.com/): For utility functions

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- [Claude AI](http://claude.ai/) that programmed most of this project
- [PHP-Parser](https://github.com/nikic/PHP-Parser) for the excellent PHP parsing capabilities
- [Symfony](https://symfony.com/) for the robust PHP components
- [Cytoscape.js](https://js.cytoscape.org/) for the graph visualization library
- All other open-source projects that made this tool possible
