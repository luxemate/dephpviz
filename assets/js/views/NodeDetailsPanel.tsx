import { FC, useEffect, useState } from "react";
import { useSigma } from "@react-sigma/core";
import { BiChevronDown, BiChevronUp } from "react-icons/bi";
import AnimateHeight from "react-animate-height";

interface NodeDetailsPanelProps {
  hoveredNode: string | null;
}

const NodeDetailsPanel: FC<NodeDetailsPanelProps> = ({ hoveredNode }) => {
  const sigma = useSigma();
  const graph = sigma.getGraph();
  const [collapsed, setCollapsed] = useState(false);
  const [dependencies, setDependencies] = useState<string[]>([]);
  const [dependents, setDependents] = useState<string[]>([]);

  useEffect(() => {
    if (!hoveredNode) {
      setDependencies([]);
      setDependents([]);
      return;
    }

    // Get outgoing connections (dependencies)
    const deps: string[] = [];
    graph.forEachOutNeighbor(hoveredNode, (neighbor, attributes) => {
      deps.push(neighbor);
    });
    setDependencies(deps);

    // Get incoming connections (dependents)
    const depts: string[] = [];
    graph.forEachInNeighbor(hoveredNode, (neighbor, attributes) => {
      depts.push(neighbor);
    });
    setDependents(depts);
  }, [hoveredNode, graph]);

  if (!hoveredNode) {
    return (
        <div className="panel">
          <h2>
            Node Details
            <button type="button" onClick={() => setCollapsed(!collapsed)}>
              {collapsed ? <BiChevronDown /> : <BiChevronUp />}
            </button>
          </h2>
          <AnimateHeight height={collapsed ? 0 : "auto"}>
            <p className="text-muted">Hover over a node to see details</p>
          </AnimateHeight>
        </div>
    );
  }

  const nodeAttributes = graph.getNodeAttributes(hoveredNode);
  const nodeType = nodeAttributes.entityType || nodeAttributes.type || 'class';
  const nodeLabel = nodeAttributes.label || hoveredNode;

  return (
      <div className="panel">
        <h2>
          Node Details
          <button type="button" onClick={() => setCollapsed(!collapsed)}>
            {collapsed ? <BiChevronDown /> : <BiChevronUp />}
          </button>
        </h2>
        <AnimateHeight height={collapsed ? 0 : "auto"}>
          <div className="node-details">
            <h3 style={{
              color: nodeType === 'class' ? '#007bff' :
                  nodeType === 'trait' ? '#28a745' :
                      nodeType === 'interface' ? '#fd7e14' : '#666'
            }}>
              {nodeLabel}
            </h3>
            <p><strong>Type:</strong> {nodeType.charAt(0).toUpperCase() + nodeType.slice(1)}</p>

            <div className="node-connections">
              <div className="dependencies">
                <h4>Dependencies ({dependencies.length})</h4>
                {dependencies.length === 0 ? (
                    <p className="text-muted">No dependencies</p>
                ) : (
                    <ul>
                      {dependencies.map((dep) => {
                        const depAttr = graph.getNodeAttributes(dep);
                        return (
                            <li key={dep} className="dep-item">
                        <span className="circle" style={{
                          backgroundColor: depAttr.type === 'class' ? '#007bff' :
                              depAttr.type === 'trait' ? '#28a745' :
                                  depAttr.type === 'interface' ? '#fd7e14' : '#666'
                        }}></span>
                              {depAttr.label || dep}
                            </li>
                        );
                      })}
                    </ul>
                )}
              </div>

              <div className="dependents">
                <h4>Dependents ({dependents.length})</h4>
                {dependents.length === 0 ? (
                    <p className="text-muted">No dependents</p>
                ) : (
                    <ul>
                      {dependents.map((dep) => {
                        const depAttr = graph.getNodeAttributes(dep);
                        return (
                            <li key={dep} className="dep-item">
                        <span className="circle" style={{
                          backgroundColor: depAttr.type === 'class' ? '#007bff' :
                              depAttr.type === 'trait' ? '#28a745' :
                                  depAttr.type === 'interface' ? '#fd7e14' : '#666'
                        }}></span>
                              {depAttr.label || dep}
                            </li>
                        );
                      })}
                    </ul>
                )}
              </div>
            </div>
          </div>
        </AnimateHeight>
      </div>
  );
};

export default NodeDetailsPanel;
