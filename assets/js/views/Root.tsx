import { FullScreenControl, SigmaContainer, ZoomControl } from "@react-sigma/core";
import { FC, useEffect, useMemo, useState } from "react";
import { BiBookContent, BiRadioCircleMarked } from "react-icons/bi";
import { BsArrowsFullscreen, BsFullscreenExit, BsZoomIn, BsZoomOut } from "react-icons/bs";
import { GrClose } from "react-icons/gr";
import { Settings } from "sigma/settings";

import { drawHover, drawLabel } from "@/utils/canvas-utils";
import { FiltersState, GraphData } from '@/types';
import DescriptionPanel from "./DescriptionPanel";
import GraphDataController from "./GraphDataController";
import GraphEventsController from "./GraphEventsController";
import GraphSettingsController from "./GraphSettingsController";
import GraphTitle from "./GraphTitle";
import SearchField from "./SearchField";
import TypesPanel from "./TypesPanel";
import ForceAtlasControl from "./ForceAtlasControl";
import NodeDetailsPanel from "./NodeDetailsPanel";
import { fetchGraphData } from '@/services/api';
import { DirectedGraph } from "graphology";
import { buildGraph, initializeGraph } from '@/utils/graph-utils';

const Root: FC = () => {
  const [graph, setGraph] = useState<DirectedGraph | null>(null);
  const [showContents, setShowContents] = useState(false);
  const [dataReady, setDataReady] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [hoveredNode, setHoveredNode] = useState<string | null>(null);

  const [filtersState, setFiltersState] = useState<FiltersState>({
    clusters: {},
    tags: {},
    nodeTypes: {
      'class': true,
      'trait': true,
      'interface': true
    },
    edgeTypes: {
      'extends': true,
      'implements': true,
      'usesTrait': true,
      'use': true
    }
  });

  const sigmaSettings: Partial<Settings> = useMemo(
      () => ({
        defaultDrawNodeLabel: drawLabel,
        defaultDrawNodeHover: drawHover,
        labelDensity: 0.07,
        labelGridCellSize: 60,
        labelRenderedSizeThreshold: 15,
        labelFont: "Public Sans, sans-serif",
        zIndex: true,
      }),
      []
  );

  // Load graph data on mount
  useEffect(() => {
    const loadGraph = async () => {
      try {
        setLoading(true);
        const graphData: GraphData = await fetchGraphData();

        // Build graph from the API data
        const newGraph = buildGraph(graphData);

        // Initialize node sizes based on connectivity
        initializeGraph(newGraph);

        setGraph(newGraph);
        setDataReady(true);
        setLoading(false);
      } catch (err) {
        console.error('Failed to load graph:', err);
        setError('Failed to load graph data. Please check the API endpoint.');
        setLoading(false);
      }
    };

    loadGraph();
  }, []);

  // Update node and edge filters
  const setNodeTypes = (nodeTypes: { [key: string]: boolean }) => {
    setFiltersState(prev => ({
      ...prev,
      nodeTypes
    }));
  };

  const setEdgeTypes = (edgeTypes: { [key: string]: boolean }) => {
    setFiltersState(prev => ({
      ...prev,
      edgeTypes
    }));
  };

  if (loading) {
    return (
        <div className="loading-state">
          <div className="loading-spinner"></div>
          <p>Loading graph data...</p>
        </div>
    );
  }

  if (error) {
    return (
        <div className="error-state">
          <h2>Error</h2>
          <p>{error}</p>
        </div>
    );
  }

  if (!graph) {
    return (
        <div className="error-state">
          <h2>No Data</h2>
          <p>No graph data available.</p>
        </div>
    );
  }

  return (
      <div id="app-root" className={showContents ? "show-contents" : ""}>
        <SigmaContainer graph={graph} settings={sigmaSettings} className="react-sigma">
          <GraphSettingsController hoveredNode={hoveredNode} />
          <GraphEventsController setHoveredNode={setHoveredNode} />
          <GraphDataController filters={filtersState} />

          {dataReady && (
              <>
                <div className="controls">
                  <div className="react-sigma-control ico">
                    <button
                        type="button"
                        className="show-contents"
                        onClick={() => setShowContents(true)}
                        title="Show caption and description"
                    >
                      <BiBookContent />
                    </button>
                  </div>
                  <ForceAtlasControl />
                  <FullScreenControl className="ico">
                    <BsArrowsFullscreen />
                    <BsFullscreenExit />
                  </FullScreenControl>

                  <ZoomControl className="ico">
                    <BsZoomIn />
                    <BsZoomOut />
                    <BiRadioCircleMarked />
                  </ZoomControl>
                </div>
                <div className="contents">
                  <div className="ico">
                    <button
                        type="button"
                        className="ico hide-contents"
                        onClick={() => setShowContents(false)}
                        title="Hide description"
                    >
                      <GrClose />
                    </button>
                  </div>
                  <GraphTitle />
                  <div className="panels">
                    <SearchField filters={filtersState} />
                    <NodeDetailsPanel hoveredNode={hoveredNode} />
                    <DescriptionPanel />
                    <TypesPanel
                        filters={filtersState}
                        setNodeTypes={setNodeTypes}
                        setEdgeTypes={setEdgeTypes}
                    />
                  </div>
                </div>
              </>
          )}
        </SigmaContainer>
      </div>
  );
};

export default Root;
