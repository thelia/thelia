export function rad2degr(rad) {
  return (rad * 180) / Math.PI;
}

export function degr2rad(degr) {
  return (degr * Math.PI) / 180;
}

export function getLatLngCenter(points) {
  if (points.length <= 0)
    return {
      latitude: 0,
      longitude: 0
    };

  let X = 0;
  let Y = 0;
  let Z = 0;

  for (const point of points) {
    let lat = degr2rad(point.latitude);
    let lng = degr2rad(point.longitude);
    X += Math.cos(lat) * Math.cos(lng);
    Y += Math.cos(lat) * Math.sin(lng);
    Z += Math.sin(lat);
  }

  const avgX = X / points.length;
  const avgY = Y / points.length;
  const avgZ = Z / points.length;

  const lng = Math.atan2(avgY, avgX);
  const hyp = Math.sqrt(avgX * avgX + avgY * avgY);
  const lat = Math.atan2(avgZ, hyp);

  return {
    latitude: rad2degr(lat),
    longitude: rad2degr(lng)
  };
}
