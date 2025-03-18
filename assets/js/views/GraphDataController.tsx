import { useSigma } from "@react-sigma/core";
import { FC, useEffect } from "react";
import { FiltersState } from '@/types';

type GraphDataControllerProps = {
  filters: FiltersState;
};

const GraphDataController: FC<GraphDataControllerProps> = ({ filters }) => {
  const sigma = useSigma();
  const graph = sigma.getGraph();

  // Apply the filters to the graph
  useEffect(() => {
    // Apply node type filtering
    if (filters.nodeTypes) {
      graph.forEachNode((node, attributes) => {
        const nodeType = attributes.entityType;
        const hidden = nodeType && filters.nodeTypes && !filters.nodeTypes[nodeType];
        graph.setNodeAttribute(node, "hidden", hidden);
      });
    }

    // Apply edge type filtering
    if (filters.edgeTypes) {
      graph.forEachEdge((edge, attributes) => {
        const edgeType = attributes.entityType;
        const hidden = edgeType && filters.edgeTypes && !filters.edgeTypes[edgeType];
        graph.setEdgeAttribute(edge, "hidden", hidden);
      });
    }

  }, [graph, filters]);

  return null;
};

export default GraphDataController;
