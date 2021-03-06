<?php
namespace Pragma\Search;

use Pragma\Search\Processor;
use Pragma\Search\Indexed;
use Pragma\Helpers\TaskLock;

class IndexerController
{
    public static function run()
    {
        TaskLock::check_lock(realpath('.').'/locks', 'indexer');

        self::loadClasses();
        Processor::index_pendings();

        TaskLock::flush(realpath('.').'/locks', 'indexer');
    }

    public static function rebuild()
    {
        TaskLock::check_lock(realpath('.').'/locks', 'indexer');

        self::loadClasses();
        Processor::rebuild();

        TaskLock::flush(realpath('.').'/locks', 'indexer');
    }

    public static function updateIndexed()
    {
        TaskLock::check_lock(realpath('.').'/locks', 'indexer-indexed');

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', "2048M");

        $loader = require realpath(__DIR__.'/../../../..') . '/autoload.php';
        $classes = array_keys($loader->getClassMap());

        $indexed = Indexed::forge()
            ->get_objects('classname');

        if (!empty($classes)) {
            foreach ($classes as $c) {
                if (strpos($c, '\\Models\\') !== false) {
                    $ref = new \ReflectionClass($c);
                    if (in_array('Pragma\\Search\\Searchable', self::class_uses_deep($c)) && !$ref->isAbstract()) {
                        if (isset($indexed[$c])) {
                            unset($indexed[$c]);
                        } else {
                            Indexed::build([
                                'classname' => $c,
                            ])->save();
                        }
                    }
                }
            }
            foreach ($indexed as $i) {
                $i->delete();
            }
        } else {
            echo "Run: composer dump-autoload -o\n";
        }

        TaskLock::flush(realpath('.').'/locks', 'indexer-indexed');
    }

    protected static function loadClasses()
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', "2048M");

        $loader = require realpath(__DIR__.'/../../../..') . '/autoload.php';
        $classes = array_keys($loader->getClassMap());

        foreach ($classes as $c) {
            if (strpos($c, '\\Models\\') !== false) {
                $ref = new \ReflectionClass($c);
                if (in_array('Pragma\\Search\\Searchable', class_uses($c)) && !$ref->isAbstract()) {
                    new $c();
                }
            }
        }
    }

    // https://www.php.net/manual/en/function.class-uses.php#110752
    protected static function class_uses_deep($class, $autoload = true)
    {
        $traits = [];
        while ($class = get_parent_class($class)) {
            $traits = array_merge(class_uses($class, $autoload), $traits);
        }
        foreach ($traits as $trait => $same) {
            $traits = array_merge(class_uses($trait, $autoload), $traits);
        }
        return array_unique($traits);
    }
}
