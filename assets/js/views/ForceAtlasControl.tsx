import { useSigma } from "@react-sigma/core";
import { FC, useEffect, useState } from "react";
import { FaPlay, FaStop } from "react-icons/fa";
import FA2Layout from "graphology-layout-forceatlas2/worker";

const ForceAtlasControl: FC = () => {
  const sigma = useSigma();
  const graph = sigma.getGraph();
  const [isRunning, setIsRunning] = useState(false);
  const [layout, setLayout] = useState<any>(null);

  // Initialize the layout
  useEffect(() => {
    // Create the FA2 layout with the worker
    const fa2Layout = new FA2Layout(graph, {
      settings: {
        gravity: 1,
        scalingRatio: 1.2,
        strongGravityMode: true,
        slowDown: 10,
        edgeWeightInfluence: 0,
        barnesHutOptimize: true,
        barnesHutTheta: 0.5,
      }
    });

    setLayout(fa2Layout);

    // Clean up on unmount
    return () => {
      if (fa2Layout && fa2Layout.isRunning()) {
        fa2Layout.stop();
      }
      if (fa2Layout) {
        fa2Layout.kill();
      }
    };
  }, [sigma, graph]);

  const toggleLayout = () => {
    if (!layout) return;

    if (isRunning) {
      layout.stop();
      setIsRunning(false);
    } else {
      layout.start();
      setIsRunning(true);
    }
  };

  return (
      <div className="ico">
        <button
            type="button"
            onClick={toggleLayout}
            title={isRunning ? "Stop layout" : "Start layout"}
            disabled={!layout}
        >
          {isRunning ? <FaStop /> : <FaPlay />}
        </button>
      </div>
  );
};

export default ForceAtlasControl;
