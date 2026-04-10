<?php

namespace App\Models;

use Database\Factories\ServerTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[
    Fillable([
        'server_id',
        'source_node_id',
        'target_node_id',
        'target_allocation_id',
        'archive_size_bytes',
        'progress',
        'status',
        'error',
    ]),
]
class ServerTransfer extends Model
{
    /** @use HasFactory<ServerTransferFactory> */
    use HasFactory;

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function sourceNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'source_node_id');
    }

    public function targetNode(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'target_node_id');
    }

    public function targetAllocation(): BelongsTo
    {
        return $this->belongsTo(Allocation::class, 'target_allocation_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['archiving', 'transferring', 'extracting', 'completing'], true);
    }

    protected function casts(): array
    {
        return [
            'archive_size_bytes' => 'integer',
            'progress' => 'integer',
        ];
    }
}
