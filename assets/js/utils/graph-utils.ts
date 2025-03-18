import { DirectedGraph } from 'graphology';
import circular from 'graphology-layout/circular';
import random from 'graphology-layout/random';
import { GraphData, NodeData, EdgeData } from '@/types';

// Node type to color mapping
export const NODE_TYPE_COLORS = {
  'class': '#007bff',
  'trait': '#28a745',
  'interface': '#fd7e14'
};

// Edge type to color mapping
export const EDGE_TYPE_COLORS = {
  'extends': '#007bff',
  'implements': '#fd7e14',
  'usesTrait': '#28a745',
  'use': '#adb5bd'
};

// Node size settings
export const MIN_NODE_SIZE = 5;
export const MAX_NODE_SIZE = 20;

/**
 * Convert API graph data to a graphology graph
 */
export const buildGraph = (graphData: GraphData): DirectedGraph => {
  const graph = new DirectedGraph();

  // Add nodes
  Object.values(graphData.nodes).forEach((node: NodeData) => {
    // Set node color based on type
    const nodeColor = NODE_TYPE_COLORS[node.type] || '#666';

    graph.addNode(node.id, {
      ...node,
      color: nodeColor,
      size: MIN_NODE_SIZE,
      entityType: node.type, // Store the PHP entity type (class, trait, interface)
      type: getNodeShape(node.type), // Set the correct Sigma.js rendering type
      hidden: false
    });
  });

  // Add edges
  Object.values(graphData.edges).forEach((edge: EdgeData) => {
    // Skip if source or target doesn't exist
    if (!graph.hasNode(edge.source) || !graph.hasNode(edge.target)) {
      return;
    }

    // Set edge color based on type
    const edgeColor = EDGE_TYPE_COLORS[edge.type] || '#adb5bd';

    graph.addEdge(edge.source, edge.target, {
      ...edge,
      color: edgeColor,
      size: edge.type === 'extends' ? 2 : 1,
      entityType: edge.type,
      type: 'line',
      hidden: false
    });
  });

  return graph;
};

/**
 * Get node shape based on type
 */
const getNodeShape = (type: string): string => {
  switch (type) {
    case 'trait':
      return 'circle';
    case 'interface':
      return 'circle';
    default:
      return 'circle';
  }
};

/**
 * Initialize the graph with positions and sizes
 */
export const initializeGraph = (graph: DirectedGraph): void => {
  // Apply circular layout as recommended by the graphology docs
  try {
    circular.assign(graph);
    console.log("Applied circular layout to the graph");
  } catch (error) {
    console.error("Error applying circular layout:", error);

    // Fall back to random layout if circular fails
    try {
      random.assign(graph, { scale: 500 });
      console.log("Applied random layout to the graph");
    } catch (randomError) {
      console.error("Error applying random layout:", randomError);
    }
  }

  // Calculate node degrees (sum of incoming and outgoing connections)
  const degrees: Record<string, number> = {};
  let minDegree = Infinity;
  let maxDegree = 0;

  graph.forEachNode((node) => {
    const degree = graph.inDegree(node) + graph.outDegree(node);
    degrees[node] = degree;

    minDegree = Math.min(minDegree, degree);
    maxDegree = Math.max(maxDegree, degree);
  });

  // If all nodes have the same degree, set them to the minimum size
  if (minDegree === maxDegree) {
    graph.forEachNode((node) => {
      graph.setNodeAttribute(node, 'size', MIN_NODE_SIZE);
    });
    return;
  }

  // Set node sizes based on their degree
  graph.forEachNode((node) => {
    const degree = degrees[node];
    const normalizedDegree = (degree - minDegree) / (maxDegree - minDegree);
    const size = MIN_NODE_SIZE + normalizedDegree * (MAX_NODE_SIZE - MIN_NODE_SIZE);

    graph.setNodeAttribute(node, 'size', size);
  });
};
