<template>
  <div class="graph-visualization">
    <div v-if="loading" class="loading">Loading graph...</div>
    <div v-if="error" class="error">{{ error }}</div>
    <div v-else ref="cytoscapeContainer" class="cytoscape-container"></div>
  </div>
</template>

<script>
import cytoscape from 'cytoscape';
import klay from 'cytoscape-klay';
import { debounce } from 'lodash';

// Register the layout algorithm
cytoscape.use(klay);

export default {
  props: {
    graphData: {
      type: Object,
      default: null
    },
    searchTerm: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      cy: null,
      loading: true,
      error: null
    };
  },
  watch: {
    graphData: {
      handler(newData) {
        if (newData) {
          this.initCytoscape();
        }
      },
      immediate: true
    },
    searchTerm: debounce(function(term) {
      this.highlightSearchResults(term);
    }, 300)
  },
  methods: {
    initCytoscape() {
      if (!this.graphData || !this.$refs.cytoscapeContainer) return;

      try {
        this.loading = true;

        this.cy = cytoscape({
          container: this.$refs.cytoscapeContainer,
          elements: this.transformGraphData(),
          style: [
            {
              selector: 'node',
              style: {
                'background-color': '#6c757d',
                'label': 'data(label)',
                'color': '#fff',
                'text-outline-color': '#6c757d',
                'text-outline-width': 2,
                'font-size': 12
              }
            },
            {
              selector: 'edge',
              style: {
                'width': 2,
                'line-color': '#adb5bd',
                'target-arrow-color': '#adb5bd',
                'target-arrow-shape': 'triangle',
                'curve-style': 'bezier'
              }
            },
            {
              selector: '.highlighted',
              style: {
                'background-color': '#f8f9fa',
                'border-width': 2,
                'border-color': '#212529',
                'color': '#212529',
                'text-outline-color': '#f8f9fa',
                'text-outline-width': 3,
                'z-index': 999
              }
            },
            {
              selector: '.dependency',
              style: {
                'background-color': '#007bff',
                'border-width': 2,
                'border-color': '#0056b3',
                'color': '#fff',
                'text-outline-color': '#007bff',
                'text-outline-width': 2,
                'z-index': 900
              }
            },
            {
              selector: '.dependent',
              style: {
                'background-color': '#28a745',
                'border-width': 2,
                'border-color': '#145523',
                'color': '#fff',
                'text-outline-color': '#28a745',
                'text-outline-width': 2,
                'z-index': 900
              }
            },
            {
              selector: '.faded',
              style: {
                'opacity': 0.3
              }
            },
            {
              selector: '.dependency-edge',
              style: {
                'line-color': '#007bff',
                'target-arrow-color': '#007bff',
                'width': 3,
                'z-index': 900
              }
            },
            {
              selector: '.dependent-edge',
              style: {
                'line-color': '#28a745',
                'target-arrow-color': '#28a745',
                'width': 3,
                'z-index': 900
              }
            }
          ],
          layout: {
            name: 'klay',
            idealEdgeLength: 500,
            nodeOverlap: 50,
            refresh: 20,
            fit: true,
            padding: 30,
            randomize: false,
            animate: false,
            nodeDimensionsIncludeLabels: true
          }
        });

        this.setupEventListeners();
        this.loading = false;
      } catch (error) {
        this.error = 'Failed to initialize graph visualization';
        this.loading = false;
        console.error(error);
      }
    },
    transformGraphData() {
      if (!this.graphData) return [];

      console.log("Graph data structure:", this.graphData);

      const elements = [];

      // Handle nodes as an object rather than array
      if (this.graphData.nodes && typeof this.graphData.nodes === 'object') {
        // Convert object values to array
        Object.values(this.graphData.nodes).forEach(node => {
          elements.push({
            data: {
              id: node.id,
              label: node.label || node.id
            }
          });
        });
      }

      // Handle edges as an object rather than array
      if (this.graphData.edges && typeof this.graphData.edges === 'object') {
        // Convert object values to array
        Object.values(this.graphData.edges).forEach(edge => {
          elements.push({
            data: {
              id: `${edge.source}-${edge.target}`,
              source: edge.source,
              target: edge.target,
              type: edge.type || 'default'
            }
          });
        });
      }

      console.log("Transformed elements:", elements);
      return elements;
    },
    setupEventListeners() {
      if (!this.cy) return;

      this.cy.on('tap', 'node', event => {
        const node = event.target;
        this.highlightConnections(node);
      });

      this.cy.on('tap', event => {
        if (event.target.isEdge === undefined && event.target.isNode === undefined) {
          this.resetHighlighting();
        }
      });
    },
    highlightConnections(node) {
      // Reset previous highlighting
      this.resetHighlighting();

      // Highlight selected node
      node.addClass('highlighted');

      // Find dependencies (outgoing edges)
      const dependencies = node.outgoers('node');
      dependencies.addClass('dependency');
      node.outgoers('edge').addClass('dependency-edge');

      // Find dependents (incoming edges)
      const dependents = node.incomers('node');
      dependents.addClass('dependent');
      node.incomers('edge').addClass('dependent-edge');

      // Fade unrelated nodes
      this.cy.nodes()
        .difference(dependencies)
        .difference(dependents)
        .difference(node)
        .addClass('faded');

      this.cy.edges()
        .difference(node.outgoers('edge'))
        .difference(node.incomers('edge'))
        .addClass('faded');
    },
    resetHighlighting() {
      this.cy.elements().removeClass('highlighted dependency dependent faded dependency-edge dependent-edge');
    },
    highlightSearchResults(term) {
      if (!this.cy || !term) {
        this.resetHighlighting();
        return;
      }

      const searchRegex = new RegExp(term, 'i');
      const matchingNodes = this.cy.nodes().filter(node =>
        searchRegex.test(node.data('label'))
      );

      if (matchingNodes.length === 0) {
        this.resetHighlighting();
        return;
      }

      this.cy.elements().addClass('faded');
      matchingNodes.removeClass('faded').addClass('highlighted');

      // If only one node matched, zoom to it
      if (matchingNodes.length === 1) {
        this.cy.animate({
          fit: {
            eles: matchingNodes,
            padding: 50
          },
          duration: 500
        });
      } else {
        // If multiple nodes matched, fit them all
        this.cy.fit(matchingNodes, 50);
      }
    }
  },
  beforeUnmount() {
    if (this.cy) {
      this.cy.destroy();
    }
  }
};
</script>

<style lang="scss" scoped>
.graph-visualization {
  width: 100%;
  height: 100%;
  position: absolute;
}

.cytoscape-container {
  width: 100%;
  height: 100%;
}

.loading, .error {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  padding: 1rem;
  border-radius: 4px;
  background-color: rgba(255, 255, 255, 0.9);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.error {
  color: #dc3545;
}
</style>
