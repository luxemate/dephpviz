import { useSigma } from "@react-sigma/core";
import { FC, useEffect } from "react";

type GraphEventsControllerProps = {
  setHoveredNode: (node: string | null) => void;
};

const GraphEventsController: FC<GraphEventsControllerProps> = ({ setHoveredNode }) => {
  const sigma = useSigma();

  /**
   * Initialize the events
   */
  useEffect(() => {
    // On mouse enter node, set the hovered node
    sigma.on("enterNode", ({ node }) => {
      setHoveredNode(node);
    });

    // On mouse leave node, reset the hovered node
    sigma.on("leaveNode", () => {
      setHoveredNode(null);
    });

    // Cleanup event listeners on component unmount
    return () => {
      sigma.removeAllListeners();
    };
  }, [sigma, setHoveredNode]);

  return null;
};

export default GraphEventsController;
