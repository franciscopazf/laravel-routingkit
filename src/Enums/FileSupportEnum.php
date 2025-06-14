<?php

namespace Fp\RoutingKit\Enums;

class FileSupportEnum
{
    const OBJECT_FILE_TREE = 'object_file_tree';
    const OBJECT_FILE_PLAIN = 'object_file_plain';
}
// This enum defines the types of file support available in the routing kit.
// - OBJECT_FILE_TREE: Represents a file that supports a tree structure of objects.
// - OBJECT_FILE_PLAIN: Represents a file that supports a plain structure of objects.
// This enum can be used to determine how to handle file operations based on the type of file support required.
// Usage example:
// $fileSupportType = FileSupportEnum::OBJECT_FILE_TREE;
// if ($fileSupportType === FileSupportEnum::OBJECT_FILE_TREE) {
//     // Handle tree structure file operations
// } elseif ($fileSupportType === FileSupportEnum::OBJECT_FILE_PLAIN) {
//     // Handle plain structure file operations
// }