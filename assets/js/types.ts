export interface NodeData {
  id: string;
  label: string;
  type: 'class' | 'trait' | 'interface';
  color?: string;
  size?: number;
  highlighted?: boolean;
}

export interface EdgeData {
  id?: string;
  source: string;
  target: string;
  type: 'extends' | 'implements' | 'usesTrait' | 'use';
  color?: string;
  size?: number;
}

export interface GraphData {
  nodes: { [key: string]: NodeData };
  edges: { [key: string]: EdgeData };
}

export interface FiltersState {
  clusters: { [key: string]: boolean };
  tags: { [key: string]: boolean };
  nodeTypes?: { [key: string]: boolean };
  edgeTypes?: { [key: string]: boolean };
}

export interface Cluster {
  key: string;
  color: string;
  clusterLabel: string;
}

export interface Tag {
  key: string;
  label: string;
  image: string;
}

export interface Dataset {
  nodes: NodeData[];
  edges: [string, string][];
  clusters: Cluster[];
  tags: Tag[];
}

// Used to define the global window object
declare global {
  interface Window {
    DePhpVizConfig?: {
      apiUrl: string;
      statusUrl: string;
    };
  }
}
