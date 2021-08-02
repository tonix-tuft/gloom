<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Gloom\Gloom;

$gloom = new Gloom();

$elements = [
  1,
  2,
  3,
  4,
  5,
  9999,
  PHP_INT_MAX,
  PHP_INT_MIN,
  -1,
  -2,
  -3,
  -4,
  -5,
  764392,
  234809342,
  4897,
];

// Adding elements.
foreach ($elements as $element) {
  $gloom->add($element);
}

// Checking elements.
foreach ($elements as $element) {
  echo PHP_EOL;
  echo json_encode(
    [
      "Gloom::possiblyHas($element)" => $gloom->possiblyHas($element),
      "Gloom::definitelyNotHas($element)" => $gloom->definitelyNotHas($element),
    ],
    JSON_PRETTY_PRINT
  );
  echo PHP_EOL;
}
echo PHP_EOL;

// Removing some elements.
echo "Removing some elements safely...";
echo PHP_EOL;
$elementsToRemove = [4, 5, 234809342, -1];
foreach ($elementsToRemove as $elementToRemove) {
  $gloom->possiblyDelete($elementToRemove);
}
foreach ($elements as $element) {
  echo PHP_EOL;
  echo json_encode(
    [
      "Gloom::possiblyHas($element)" => $gloom->possiblyHas($element),
      "Gloom::definitelyNotHas($element)" => $gloom->definitelyNotHas($element),
    ],
    JSON_PRETTY_PRINT
  );
  echo PHP_EOL;
}
echo PHP_EOL;
