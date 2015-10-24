<?php
namespace History\Entities\Models;

class Request extends AbstractModel
{
    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'link',
        'condition',
        'approval',
        'passed',
    ];

    //////////////////////////////////////////////////////////////////////
    //////////////////////////// RELATIONSHIPS ///////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function votes()
    {
        return $this->hasManyThrough(Vote::class, Question::class);
    }
}
