import { NodeDisplayData, PartialButFor } from "sigma/types";
import { Settings } from "sigma/settings";

export function drawLabel(
    context: CanvasRenderingContext2D,
    data: PartialButFor<NodeDisplayData, "x" | "y" | "size" | "label" | "color">,
    settings: Settings
): void {
  if (!data.label) return;

  const size = settings.labelSize;
  const font = settings.labelFont;
  const weight = settings.labelWeight;
  const color = settings.labelColor.color;

  context.font = `${weight} ${size}px ${font}`;
  context.fillStyle = color || 'black';
  context.shadowOffsetX = 0;
  context.shadowOffsetY = 0;
  context.shadowBlur = 6;
  context.shadowColor = '#ffffff';
  context.fillText(data.label, data.x, data.y + data.size + size + 3);
}

export function drawHover(
    context: CanvasRenderingContext2D,
    data: PartialButFor<NodeDisplayData, "x" | "y" | "size" | "label" | "color">,
    settings: Settings
): void {
  const size = data.size;
  const color = data.color;

  // Draw border
  context.beginPath();
  context.arc(data.x, data.y, size + 3, 0, Math.PI * 2);
  context.closePath();
  context.fillStyle = '#ffffff';
  context.fill();

  // Draw node
  context.beginPath();
  context.arc(data.x, data.y, size, 0, Math.PI * 2);
  context.closePath();
  context.fillStyle = color;
  context.fill();
}
