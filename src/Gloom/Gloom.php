<?php

/*
 * Copyright (c) 2021 Anton Bagdatyev (Tonix)
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 */

namespace Gloom;

use IntHash\Hasher;

/**
 * A class representing a counting bloom filter with deletion capabilities which may lead to false negatives if not carefully used.
 *
 * @author Anton Bagdatyev (Tonix) <antonytuft@gmail.com>
 */
class Gloom {
  /**
   * Default size of the bloom filter (also known as the `m` parameter in academic papers).
   */
  const DEFAULT_SIZE = 8000009;

  /**
   * @var int
   */
  protected $size;

  /**
   * @var callable[]
   */
  protected $hashFunctions;

  /**
   * @var array
   */
  protected $bloomFilter = [];

  /**
   * @var int
   */
  protected $maxNumberOfCollisionsEverRecordedPerBucket = 0;

  /**
   * Constructs a new bloom filter.
   *
   * @param array $options An optional array of options:
   *
   *                           - 'size' (int): The size of the bloom filter (also known as the `m` parameter in academic papers);
   *
   *                           - 'hashFunctions' (callable[]): An array of hash functions to use for storing and retrieving each element of the bloom filter
   *                                                           (used to hash the elements and to determine the `k` parameter, i.e. the number of hash functions).
   *                                                           Each function is going to receive an element and must return an integer representing the hash of the element.
   */
  public function __construct($options = []) {
    ['size' => $size, 'hashFunctions' => $hashFunctions] = $options + [
      'size' => self::DEFAULT_SIZE,
      'hashFunctions' => $this->defaultHashFunctions(),
    ];

    $this->size = $size;
    $this->hashFunctions = $hashFunctions;
  }

  /**
   * Returns the default hash functions used by a Gloom bloom filter.
   *
   * @return callable[] An array of hash functions to use for storing and retrieving each element of the bloom filter.
   *                    Each function is going to receive an element and must return an integer representing the hash of the element.
   */
  protected function defaultHashFunctions() {
    $hasher = new Hasher();
    return [
      // 1st
      function ($element) use ($hasher) {
        $hash = $hasher->hash($element);
        return $hash;
      },

      // 2nd
      function ($element) use ($hasher) {
        $hash = $hasher->hash($element, [
          'prime' => 61,
          'factor' => 653,
        ]);
        return $hash;
      },

      // 3rd
      function ($element) use ($hasher) {
        $hash = $hasher->hash($element, [
          'prime' => 67,
          'factor' => 223,
        ]);
        return $hash;
      },

      // 4th
      function ($element) use ($hasher) {
        $hash = $hasher->hash($element, [
          'prime' => 71,
          'factor' => 179,
        ]);
        return $hash;
      },

      // 5th
      function ($element) use ($hasher) {
        $hash = $hasher->hash($element, [
          'prime' => 73,
          'factor' => 101,
        ]);
        return $hash;
      },

      // 6th
      function ($element) use ($hasher) {
        $hash = $hasher->hash($element, [
          'prime' => 79,
          'factor' => 419,
        ]);
        return $hash;
      },
    ];
  }

  /**
   * Computes the bloom filter's indices for the given element.
   *
   * @param mixed $element The element.
   * @return int[] The indices.
   */
  protected function computeIndices($element) {
    $size = $this->size;
    $indices = array_map(function ($hashFunction) use ($element, $size) {
      $hash = $hashFunction($element);
      $index = $hash % $size;
      $index = $index > 0 ? $index : $size + $index;
      return $index;
    }, $this->hashFunctions);
    return $indices;
  }

  /**
   * Adds an element to the bloom filter.
   *
   * @param mixed $element The element to add.
   * @return void
   */
  public function add($element) {
    $indices = $this->computeIndices($element);
    $duplicateIndicesMap = [];
    foreach ($indices as $index) {
      if (empty($this->bloomFilter[$index])) {
        $this->bloomFilter[$index] = [
          'first_element_for_index' => $element,
          'count' => 0,
        ];
      }
      if (empty($duplicateIndicesMap[$index])) {
        $duplicateIndicesMap[$index] = true;
        $this->bloomFilter[$index]['count']++;
        if (
          $this->maxNumberOfCollisionsEverRecordedPerBucket <
          $this->bloomFilter[$index]['count']
        ) {
          $this->maxNumberOfCollisionsEverRecordedPerBucket =
            $this->bloomFilter[$index]['count'];
        }
      }
    }
  }

  /**
   * Tests whether the bloom filter possibly has the given element (including false positives).
   * This method is the opposite of {@link Gloom::definitelyNotHas()}.
   *
   * @param mixed $element The element.
   * @return bool TRUE if the element is in the bloom filter or it is a false positive, FALSE otherwise.
   *              NOTE: If an element is added with {@link Gloom::add()} several times,
   *                    this method could lead to false positives if the element is subsequently removed with {@link Gloom::riskyDelete()}.
   */
  public function possiblyHas($element) {
    $indices = $this->computeIndices($element);
    $possiblyHasElement = $this->possiblyHasInternal($element, $indices);
    return $possiblyHasElement;
  }

  /**
   * Internal method used to implement the logic of the {@link Gloom::possiblyHas()} method using the computed indices.
   *
   * @param mixed $element The element.
   * @return bool TRUE if the element is in the bloom filter or it is a false positive, FALSE otherwise.
   */
  protected function possiblyHasInternal($element, $indices) {
    foreach ($indices as $index) {
      $bucket = $this->bloomFilter[$index] ?? null;
      if (empty($bucket)) {
        return false;
      }
      if ($bucket['first_element_for_index'] === $element) {
        return true;
      }
    }
    return true;
  }

  /**
   * Tests whether the bloom filter definitely doesn't have the given element (true negatives).
   * This method is the opposite of {@link Gloom::possiblyHas()}.
   *
   * @param mixed $element The element.
   * @return bool TRUE if the element is definitely not in the bloom filter (a true negative), FALSE otherwise.
   *              NOTE: If {@link Gloom::riskyDelete()} has been called on elements never added to the bloom filter,
   *                    this method could lead to false negatives for other elements.
   */
  public function definitelyNotHas($element) {
    return !$this->possiblyHas($element);
  }

  /**
   * Possibly deletes an element.
   *
   * @param mixed $element The element.
   * @return void
   */
  public function possiblyDelete($element) {
    $indices = $this->computeIndices($element);
    $definitelyNotHas = !$this->possiblyHasInternal($element, $indices);
    if ($definitelyNotHas) {
      return;
    }
    foreach ($indices as $index) {
      if (
        !empty($this->bloomFilter[$index]) &&
        $this->bloomFilter[$index]['first_element_for_index'] === $element
      ) {
        $this->bloomFilter[$index]['count']--;
        if ($this->bloomFilter[$index]['count'] < 1) {
          $this->bloomFilter[$index] = null;
        }
      }
    }
  }

  /**
   * Performs a risky deletion of an element, which may lead to false negatives if an element is removed and it wasn't never added before.
   *
   * @param mixed $element The element.
   * @return void
   */
  public function riskyDelete($element) {
    $indices = $this->computeIndices($element);
    $definitelyNotHas = !$this->possiblyHasInternal($element, $indices);
    if ($definitelyNotHas) {
      return;
    }
    foreach ($indices as $index) {
      if (!empty($this->bloomFilter[$index])) {
        $this->bloomFilter[$index]['count']--;
        if ($this->bloomFilter[$index]['count'] < 1) {
          $this->bloomFilter[$index] = null;
        }
      }
    }
  }
}
