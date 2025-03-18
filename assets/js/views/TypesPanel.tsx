import React, { FC } from "react";
import { BiChevronDown, BiChevronUp } from "react-icons/bi";
import AnimateHeight from "react-animate-height";
import { FiltersState } from '@/types';

interface TypesPanelProps {
  filters: FiltersState;
  setNodeTypes: (types: { [key: string]: boolean }) => void;
  setEdgeTypes: (types: { [key: string]: boolean }) => void;
}

const TypesPanel: FC<TypesPanelProps> = ({ filters, setNodeTypes, setEdgeTypes }) => {
  const [collapsed, setCollapsed] = React.useState(false);

  // Default node types
  const nodeTypes = filters.nodeTypes || {
    'class': true,
    'trait': true,
    'interface': true
  };

  // Default edge types
  const edgeTypes = filters.edgeTypes || {
    'extends': true,
    'implements': true,
    'usesTrait': true,
    'use': true
  };

  // Toggle node type filter
  const toggleNodeType = (type: string) => {
    const newNodeTypes = { ...nodeTypes };
    newNodeTypes[type] = !newNodeTypes[type];
    setNodeTypes(newNodeTypes);
  };

  // Toggle edge type filter
  const toggleEdgeType = (type: string) => {
    const newEdgeTypes = { ...edgeTypes };
    newEdgeTypes[type] = !newEdgeTypes[type];
    setEdgeTypes(newEdgeTypes);
  };

  // Node type colors mapping
  const nodeTypeColors = {
    'class': '#007bff',
    'trait': '#28a745',
    'interface': '#fd7e14'
  };

  // Edge type colors mapping
  const edgeTypeColors = {
    'extends': '#007bff',
    'implements': '#fd7e14',
    'usesTrait': '#28a745',
    'use': '#adb5bd'
  };

  return (
      <div className="panel">
        <h2>
          PHP Types
          <button type="button" onClick={() => setCollapsed(!collapsed)}>
            {collapsed ? <BiChevronDown /> : <BiChevronUp />}
          </button>
        </h2>
        <AnimateHeight height={collapsed ? 0 : "auto"}>
          <p className="text-muted">Filter by node types:</p>
          <ul>
            {Object.entries(nodeTypes).map(([type, enabled]) => (
                <li key={`node-${type}`} className="caption-row">
                  <input
                      type="checkbox"
                      id={`node-${type}`}
                      checked={enabled}
                      onChange={() => toggleNodeType(type)}
                  />
                  <label htmlFor={`node-${type}`}>
                <span
                    className="circle"
                    style={{ backgroundColor: nodeTypeColors[type as keyof typeof nodeTypeColors] || '#666' }}
                ></span>
                    <span className="node-label">{type.charAt(0).toUpperCase() + type.slice(1)}</span>
                  </label>
                </li>
            ))}
          </ul>

          <p className="text-muted">Filter by edge types:</p>
          <ul>
            {Object.entries(edgeTypes).map(([type, enabled]) => (
                <li key={`edge-${type}`} className="caption-row">
                  <input
                      type="checkbox"
                      id={`edge-${type}`}
                      checked={enabled}
                      onChange={() => toggleEdgeType(type)}
                  />
                  <label htmlFor={`edge-${type}`}>
                <span
                    className="circle"
                    style={{ backgroundColor: edgeTypeColors[type as keyof typeof edgeTypeColors] || '#666' }}
                ></span>
                    <span className="node-label">{type.charAt(0).toUpperCase() + type.slice(1)}</span>
                  </label>
                </li>
            ))}
          </ul>
        </AnimateHeight>
      </div>
  );
};

export default TypesPanel;
