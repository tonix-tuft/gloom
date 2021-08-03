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

// Removing some elements safely.
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

// Removing an element already removed safely (doesn't corrupt the bloom filter).
echo "Removing an already removed element safely...";
echo PHP_EOL;
$elementsToRemove = [-1];
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

// Removing an element already removed in a risky way (it may corrupt the bloom filter in some cases,
// i.e. when all the hashes of the element lead to the same indices in the bloom filter as for another element,
// or if the element was never added to the bloom filter but its hashes lead to not empty indices in the filter).
echo "Removing an already removed element in a risky way...";
echo PHP_EOL;
$elementsToRemove = [-1];
foreach ($elementsToRemove as $elementToRemove) {
  $gloom->riskyDelete($elementToRemove);
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
