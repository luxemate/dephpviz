import { FC, useState } from "react";
import { BiChevronDown, BiChevronUp } from "react-icons/bi";
import AnimateHeight from "react-animate-height";

const DescriptionPanel: FC = () => {
  const [collapsed, setCollapsed] = useState(false);

  return (
      <div className="panel">
        <h2>
          About DePhpViz
          <button type="button" onClick={() => setCollapsed(!collapsed)}>
            {collapsed ? <BiChevronDown /> : <BiChevronUp />}
          </button>
        </h2>
        <AnimateHeight height={collapsed ? 0 : "auto"}>
          <p>
            DePhpViz is a PHP dependency visualization tool that helps you understand the relationships
            between classes in your PHP codebase.
          </p>
          <p>
            <strong>How to use:</strong>
          </p>
          <ul>
            <li>
              <strong>Hover</strong> over nodes to highlight connections.
            </li>
            <li>
              <strong>Click</strong> on a node to focus on it and its dependencies.
            </li>
            <li>
              <strong>Search</strong> for classes, traits, or interfaces by name.
            </li>
            <li>
              <strong>Filter</strong> by node types to focus on specific elements.
            </li>
            <li>
              <strong>Zoom in/out</strong> using the controls or mouse wheel.
            </li>
          </ul>
          <p>
            <strong>Legend:</strong>
          </p>
          <ul>
            <li>
              <span style={{ display: "inline-block", width: "12px", height: "12px", borderRadius: "50%", backgroundColor: "#007bff", marginRight: "5px" }}></span>
              <strong>Class</strong> - Regular PHP classes
            </li>
            <li>
              <span style={{ display: "inline-block", width: "12px", height: "12px", borderRadius: "50%", backgroundColor: "#28a745", marginRight: "5px" }}></span>
              <strong>Trait</strong> - PHP Traits
            </li>
            <li>
              <span style={{ display: "inline-block", width: "12px", height: "12px", borderRadius: "50%", backgroundColor: "#fd7e14", marginRight: "5px" }}></span>
              <strong>Interface</strong> - PHP Interfaces
            </li>
          </ul>
          <p>
            <strong>Connections:</strong>
          </p>
          <ul>
            <li>
              <span style={{ display: "inline-block", width: "20px", height: "2px", backgroundColor: "#007bff", marginRight: "5px" }}></span>
              <strong>Extends</strong> - Class inheritance
            </li>
            <li>
              <span style={{ display: "inline-block", width: "20px", height: "2px", backgroundColor: "#fd7e14", marginRight: "5px" }}></span>
              <strong>Implements</strong> - Interface implementation
            </li>
            <li>
              <span style={{ display: "inline-block", width: "20px", height: "2px", backgroundColor: "#28a745", marginRight: "5px" }}></span>
              <strong>Uses Trait</strong> - Trait usage
            </li>
            <li>
              <span style={{ display: "inline-block", width: "20px", height: "2px", backgroundColor: "#adb5bd", marginRight: "5px" }}></span>
              <strong>Use</strong> - General dependency
            </li>
          </ul>
        </AnimateHeight>
      </div>
  );
};

export default DescriptionPanel;
