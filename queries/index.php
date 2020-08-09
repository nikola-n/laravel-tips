<?php

//1. Add scope function in a model

//public
function scopeOfType(Builder $query, $type)
{
    return $query->where('type', $type);
}

//Then you can access it like: User::ofType('premium')->get();

//2. If want to update user roles to first class citizents: have PremiumUser, Admin, and they all point to the same table class etc.
//then we need global scope

class PremiumUser extends User
{
    protected static function boot()
    {
        static::addGlobalScope(new PremiumUserScope);
        parent::boot();
    }
}

class PremiumUserScope implements Scope
{

    public function apply(Builder $q, Model $model)
    {
        $q->where('type', 'premium'); //PremiumUser::all()
    }

    public function remove(Builder $q, Model $model)
    {
        //fetch the query builder
        $query = $q->getQuery();

        //wheres property in the query object is an array of arrays that have information
        //for the queries
        foreach ((array)$query->wheres as $key => $where) {
            if ($where['column'] == 'type') {
                unset($query->wheres[$key]);
            }
            //returns all values form associative array
            $query->wheres = array_values($query->wheres);
        }
    }
}

interface Scope
{
    public function apply(Builder $q, Model $model);

    public function remove(Builder $q, Model $model);
}

//3. sort a collection vs sort it on query side
//director has many movies
//public
function scopeBusy()
{
    //query
    return static::lefJoin('movies', 'movies.director_id', '=', 'directors.id')
        ->select('directors.id', 'directors.name', \DB::raw('count(movies.id) as movie_count'))
        ->groupBy('directors.id')
        ->orderBy('movie_count', 'desc')
        ->get();
    //collection
    //return static::with('movies')->get()->sortByDesc(function ($director) {
    //   return $director->movies()->count();
    //});
}

//if the queries gets too big we can create repository class
//only for a couple of methods
class DirectorRepository
{
    public function busy()
    {
        return Director::lefJoin('movies', 'movies.director_id', '=', 'directors.id')
            ->select('directors.id', 'directors.name', \DB::raw('count(movies.id) as movie_count'))
            ->groupBy('directors.id')
            ->orderBy('movie_count', 'desc')
            ->get();
    }
}

// query object approach, a class that has one responsibility, execute queries

//it can implement scope interface
class BusyDirectors
{
    protected $scoped;

    public function __construct($scoped)
    {
        $this->scoped = $scoped ?: new Director;
    }

    public function get()
    {
        return $this->scoped->lefJoin('movies', 'movies.director_id', '=', 'directors.id')
            ->select('directors.id', 'directors.name', \DB::raw('count(movies.id) as movie_count'))
            ->groupBy('directors.id')
            ->orderBy('movie_count', 'desc')
            ->get();
    }
}

Route::get('/', function () {

    //ambiguous column name error -> doesnt know which id you are pointing.
    $earlyAdopters = App\Director::where('directors.id', '<=', 10);
    return (new BusyDirectors($earlyAdopters))->get();
});