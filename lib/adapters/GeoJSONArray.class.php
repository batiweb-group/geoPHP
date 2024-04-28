<?php
/**
 * GeoJSONArray class : a geojson reader/writer.
 *
 * Note that it will always return a GeoJSON geometry. This
 * means that if you pass it a feature, it will return the
 * geometry of that feature strip everything else.
 */
class GeoJSONArray extends GeoAdapter
{
  /**
   * @var null|callable $convertPoint
   */
  public static $convertPoint;

  /**
   * Given an object or a string, return a Geometry
   *
   * @param mixed $input The GeoJSON string or object
   *
   * @return object Geometry
   */
  public function read($input) {
    if (is_string($input)) {
      $input = json_decode($input, true, 2048);
    }
    if (!is_array($input)) {
      throw new Exception('Invalid JSON');
    }
    if (!is_string($input['type'])) {
      throw new Exception('Invalid JSON');
    }

    // Check to see if it's a FeatureCollection
    if ($input['type'] == 'FeatureCollection') {
      $geoms = array();
      foreach ($input['features'] as $feature) {
        $geoms[] = $this->read($feature);
      }
      return geoPHP::geometryReduce($geoms);
    }

    // Check to see if it's a Feature
    if ($input['type'] == 'Feature') {
      return $this->read($input['geometry']);
    }

    // It's a geometry - process it
    return $this->objToGeom($input);
  }

  private function objToGeom($obj) {
    $type = $obj['type'];

    if ($type == 'GeometryCollection') {
      return $this->objToGeometryCollection($obj);
    }
    $method = 'arrayTo' . $type;
    return $this->$method($obj['coordinates']);
  }

  private function arrayToPoint($array) {
    if (!empty($array)) {
      $point = new Point($array[0], $array[1]);
      if(self::$convertPoint) {
        $point = call_user_func(self::$convertPoint, $point);
      }
      return $point;
    }
    else {
      return new Point();
    }
  }

  private function arrayToLineString($array) {
    $points = array();
    foreach ($array as $comp_array) {
      $points[] = $this->arrayToPoint($comp_array);
    }
    return new LineString($points);
  }

  private function arrayToPolygon($array) {
    $lines = array();
    foreach ($array as $comp_array) {
      $lines[] = $this->arrayToLineString($comp_array);
    }
    return new Polygon($lines);
  }

  private function arrayToMultiPoint($array) {
    $points = array();
    foreach ($array as $comp_array) {
      $points[] = $this->arrayToPoint($comp_array);
    }
    return new MultiPoint($points);
  }

  private function arrayToMultiLineString($array) {
    $lines = array();
    foreach ($array as $comp_array) {
      $lines[] = $this->arrayToLineString($comp_array);
    }
    return new MultiLineString($lines);
  }

  private function arrayToMultiPolygon($array) {
    $polys = array();
    foreach ($array as $comp_array) {
      $polys[] = $this->arrayToPolygon($comp_array);
    }
    return new MultiPolygon($polys);
  }

  private function objToGeometryCollection($obj) {
    $geoms = array();
    if (empty($obj['geometries'])) {
      throw new Exception('Invalid GeoJSON: GeometryCollection with no component geometries');
    }
    foreach ($obj['geometries'] as $comp_object) {
      $geoms[] = $this->objToGeom($comp_object);
    }
    return new GeometryCollection($geoms);
  }

  /**
   * Serializes an object into a geojson string
   *
   *
   * @param Geometry $obj The object to serialize
   *
   * @return string The GeoJSON string
   */
  public function write(Geometry $geometry) {
    return $this->getArray($geometry);
  }

  public function getArray(Collection $geometry) {
    if ($geometry->getGeomType() == 'GeometryCollection') {
      $component_array = array();
      foreach ($geometry->components as $component) {
        $component_array[] = array(
          'type' => $component->geometryType(),
          'coordinates' => $component->asArray(),
        );
      }
      return array(
        'type'=> 'GeometryCollection',
        'geometries'=> $component_array,
      );
    }
    else return array(
      'type'=> $geometry->getGeomType(),
      'coordinates'=> $geometry->asArray(),
    );
  }
}


