export function hasInk(strokes) {
    const points = strokes.flat();
    if (points.length < 5) return false;
    let length = 0;
    for (const stroke of strokes) for (let i = 1; i < stroke.length; i++) length += Math.hypot(stroke[i][0] - stroke[i-1][0], stroke[i][1] - stroke[i-1][1]);
    const xs = points.map(point => point[0]), ys = points.map(point => point[1]);
    return length >= 0.12 && Math.max(...xs) - Math.min(...xs) >= 0.03 && Math.max(...ys) - Math.min(...ys) >= 0.02;
}
