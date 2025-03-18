import { useSetSettings, useSigma } from "@react-sigma/core";
import { Attributes } from "graphology-types";
import { FC, PropsWithChildren, useEffect } from "react";

import { drawHover, drawLabel } from '@/utils/canvas-utils';
import useDebounce from "@/utils/use-debounce";

const NODE_FADE_COLOR = "#bbb";
const EDGE_FADE_COLOR = "#eee";

const GraphSettingsController: FC<PropsWithChildren<{ hoveredNode: string | null }>> = ({ children, hoveredNode }) => {
  const sigma = useSigma();
  const setSettings = useSetSettings();
  const graph = sigma.getGraph();

  // Debounce the hovered node value to improve performance
  const debouncedHoveredNode = useDebounce(hoveredNode, 40);

  /**
   * Initialize settings that require the graph and sigma instance
   */
  useEffect(() => {
    const hoveredColor: string = (debouncedHoveredNode && sigma.getNodeDisplayData(debouncedHoveredNode)?.color) || "";

    setSettings({
      defaultDrawNodeLabel: drawLabel,
      defaultDrawNodeHover: drawHover,
      // Node reducer to highlight connections and fade others
      nodeReducer: (node: string, data: Attributes) => {
        if (debouncedHoveredNode) {
          return node === debouncedHoveredNode ||
          graph.hasEdge(node, debouncedHoveredNode) ||
          graph.hasEdge(debouncedHoveredNode, node)
              ? { ...data, zIndex: 1 }
              : { ...data, zIndex: 0, label: "", color: NODE_FADE_COLOR, highlighted: false };
        }
        return data;
      },
      // Edge reducer to highlight connections and hide others
      edgeReducer: (edge: string, data: Attributes) => {
        if (debouncedHoveredNode) {
          return graph.hasExtremity(edge, debouncedHoveredNode)
              ? { ...data, color: hoveredColor, size: 4 }
              : { ...data, color: EDGE_FADE_COLOR, hidden: true };
        }
        return data;
      },
    });
  }, [sigma, graph, debouncedHoveredNode]);

  /**
   * Update node and edge reducers when a node is hovered
   */
  useEffect(() => {
    const hoveredColor: string = (debouncedHoveredNode && sigma.getNodeDisplayData(debouncedHoveredNode)?.color) || "";

    sigma.setSetting(
        "nodeReducer",
        debouncedHoveredNode
            ? (node, data) =>
                node === debouncedHoveredNode ||
                graph.hasEdge(node, debouncedHoveredNode) ||
                graph.hasEdge(debouncedHoveredNode, node)
                    ? { ...data, zIndex: 1 }
                    : { ...data, zIndex: 0, label: "", color: NODE_FADE_COLOR, highlighted: false }
            : null,
    );
    sigma.setSetting(
        "edgeReducer",
        debouncedHoveredNode
            ? (edge, data) =>
                graph.hasExtremity(edge, debouncedHoveredNode)
                    ? { ...data, color: hoveredColor, size: 4 }
                    : { ...data, color: EDGE_FADE_COLOR, hidden: true }
            : null,
    );
  }, [debouncedHoveredNode]);

  return <>{children}</>;
};

export default GraphSettingsController;
