<?php

namespace Phaseolies\Database\Migration;

// This abstract class defines the structure for a database migration.
// It is meant to be extended by concrete migration classes that implement
// the logic for applying (up) and reverting (down) a database change.

abstract class Migration
{
    // Run up() and down() inside a transaction on drivers that can roll back schema
    // changes (PostgreSQL, SQLite). Set to false for statements that cannot run in a
    // transaction, such as PostgreSQL's CREATE INDEX CONCURRENTLY.
    public bool $withinTransaction = true;

    // The 'up' method should contain the logic to apply the migration.
    // For example, creating tables, adding columns, or modifying indexes.
    abstract public function up(): void;

    // The 'down' method should reverse the changes made in the 'up' method.
    // This is useful for rolling back migrations.
    abstract public function down(): void;
}
