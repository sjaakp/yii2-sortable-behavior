Yii2 Sortable
=============

This package contains five classes to handle the sorting of ActiveRecords:

- **SortableGridView** - extended GridView widget;
- **SortableListView** - extended ListView widget;
- **Sortable** - ActiveRecord Behavior to handle the sorting of the records themselves, or of the one-to-many related records;
- **PivotRecord** - base class for the ActiveRecord of the pivot table in a many-to-many relation.
- **MMSortable** - ActiveRecord Behavior to handle the sorting of many-to-many related records.

A demonstration of the **Sortable** suit is [here](http://www.sjaakpriester.nl/director/index).

## Installation ##

The preferred way to install **Sortable** is through [Composer](https://getcomposer.org/). Either add the following to the require section of your `composer.json` file:

`"sjaakp/yii2-sortable-behavior": "*"` 

Or run:

`$ php composer.phar require sjaakp/yii2-sortable-behavior "*"` 

You can manually install **Sortable** by [downloading the source in ZIP-format](https://github.com/sjaakp/yii2-sortable-behavior/archive/master.zip).

## SortableGridView and SortableListView ##

These widgets are derived from the standard GridView and ListView classes, but have one extra capability: the items can be moved to another position by means of drag and drop (using the jQuery UI Sortable functionality). If an item is dropped on a new position, an Ajax-message is posted with the following data:

- `key`: the value of the item's primary key;
- `pos`: the zero-indexed new position of the item.

**SortableGridView** and **SortableListView** have three extra configurable properties:

#### orderUrl ####

`array|string`. The URL which is called after a sorting operation.
The format is that of `yii\helpers\Url::toRoute`.

#### sortOptions ####

`array`. The options for the jQuery sortable object. See [http://api.jqueryui.com/sortable/](http://api.jqueryui.com/sortable/).

Notice that the options `'items'`, `'helper'`, and `'update'` will be overwritten.

Default: `[]` (empty array).

#### sortAxis ####

`boolean|string` The `'axis'` option for the jQuery sortable. If `false`, it is not set. Default: `'y'`.

## Sortable ##

With this Behavior, an ActiveRecord becomes 'sortable'. It has one configurable property and one extra method:

#### orderAttribute ####

`string|array`. The order attribute(s) of the ActiveRecord.

This can take the following values:

 - `string` - the order attribute name;
 - `array` of:
     * `string` - the order attribute name,
     * `foreignKeyName => orderAttrName` - limit ordering to ActiveRecords with the same foreign key value, i.e. of the same owner.

Default is `"ord"`. 

#### order() ####



`public function order( $newPosition, $foreignKeyName = null )` 

This method puts the owner on the new position `$newPosition` by manipulating the order attribute. The order attribute is a zero-indexed, contiguous integer.

If `$foreignKeyName` is `null` (default) all the records are ordered. 

If it is a string, the ordering is restricted to the records with the same value of `$foreignKeyName`. `$foreignKeyName` must be a key in `orderAttribute`. This comes in handy with one-to-many relations.

----------

### Usage scenario 1  ###
## Simple sorting ##

Suppose we have a very simple table of movie titles:

    CREATE TABLE movie (
  	  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  	  ord int(10) unsigned NOT NULL,
  	  title tinytext NOT NULL,
	  PRIMARY KEY (id)
	)

Where `ord` will be our order attribute.

We can make the `Movie` ActiveRecord sortable like this:

	class Movie extends ActiveRecord
	{
	    public function behaviors( ) {
    	    return [
    	        [
    	            'class' => 'sjaakp\sortable\Sortable',
    	        ],
    	    ];
    	}
		...
	}

In the controller we define an `index` action and an `order` action:

	class MovieController extends Controller
	{
		...
	    public function actionIndex( )
    	{
    	    $dataProvider = new ActiveDataProvider( [
    	        'query' => Movie::find( )->orderBy( 'ord' ),	// notice the orderBy clause
    	        'sort' => false,
    	        'pagination' => false
    	    ] );

    	    return $this->render( 'index', [
    	        'dataProvider' => $dataProvider,
    	    ] );
    	}
		...
	    public function actionOrder( )   {
	        $post = Yii::$app->request->post( );
	        if (isset( $post['key'], $post['pos'] ))   {
	            $this->findModel( $post['key'] )->order( $post['pos'] );
	        }
	    }
		...
	}

In the `index` view, we use a **SortableGridView**:

    use sjaakp\sortable\SortableGridView;
	...
    <?= SortableGridView::widget( [
        'dataProvider' => $dataProvider,
        'orderUrl' => ['order'],
        'columns' => [
			...
            'title:ntext',
			...
        ],
		...
    ] ); ?>

And bingo! The list of movie titles is now sortable by drag and drop, say on preference.

----------

### Usage scenario 2  ###
## One-to-many sorting ##

Suppose we also have a list of directors. Each director has many movies, each movie belongs to one director (thinking of the Coen brothers, I know this is not necessarily true in reality).

We add two columns to our `movie` table:

    CREATE TABLE movie (
  	  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  	  ord int(10) unsigned NOT NULL,
  	  title tinytext NOT NULL,
	  director_id int(10) unsigned NOT NULL,
	  director_ord int(10) unsigned NOT NULL,
	  PRIMARY KEY (id)
	)

Where `director_ord` is the order attribute just for movies belonging to the same director.

In the `Director` model we define an one-to-many relation, like we would normally do (notice the `orderBy` clause): 

	class Director extends ActiveRecord	{
		...
    	public function getMovies( ) {
        	return $this->hasMany( Movie::className( ), ['director_id' => 'id'] )
        	    ->orderBy( 'director_ord' );
    	}
		...
	}

The `Movie` model is sortable like before, but with another `orderAttribute`:

	class Movie extends ActiveRecord
	{
	    public function behaviors( ) {
    	    return [
    	        [
    	            'class' => 'sjaakp\sortable\Sortable',
	                'orderAttribute' => [
	                    'director_id' => 'director_ord'
    	            ]
    	        ],
    	    ];
    	}
		...
	}

This time, `DirectorController` sports an extra action:

	class DirectorController extends Controller
	{
		...
	    public function actionMovieOrder( )   {
	        $post = Yii::$app->request->post( );
	        if (isset( $post['key'], $post['pos'] ))   {
	            $movie = Movie::findOne( $post['key'] );
	            if ($movie) $movie->order( $post['pos'], 'director_id' );
	        }
	    }
		...
	}

Let's now use a **SortableGridView** to display all the movies of the director in `director/view`:

	use sjaakp\sortable\SortableGridView;
	...
	$movies = new ActiveDataProvider( [
    	'query' => $model->getMovies( ),  // Do not use $model->movies, it returns array of Movies in stead of an ActiveQueryInterface
    	'sort' => false,
    	'pagination' => false
	] );
	...
    <h1><?= Html::encode( $model->name ) ?></h1>
	...
    <?= SortableGridView::widget( [
        'dataProvider' => $movies,
        'orderUrl' => ['movie-order'],
        'columns' => [
			...
            'title:ntext',
			...
        ],
		...
    ] ); ?>
	
Now each director's view shows a sortable list of his or her movies.

It's easy to combine Usage scenario's 1 and 2, so that *all* movies are sortable in `movie/index` and only the director's movies in `director/view`. Just initialize `Movie`'s **Sortable** behavior like this:

	class Movie extends ActiveRecord
	{
	    public function behaviors( ) {
    	    return [
    	        [
    	            'class' => 'sjaakp\sortable\Sortable',
	                'orderAttribute' => [
						'ord',
	                    'director_id' => 'director_ord'
    	            ]
    	        ],
    	    ];
    	}
		...
	}

----------

## PivotRecord ##

This is the base ActiveRecord for the pivot table of two sortable Models in a many-to-many relation.

The ordering information is stored in the pivot table as well.

A pivot table might look something like this:

	CREATE TABLE actor_movie (
	  actor_id int(10) unsigned NOT NULL,	# actor's primary key
	  movie_id int(10) unsigned NOT NULL,	# movie's primary key
	  actor_ord int(10) unsigned NOT NULL,	# actor's order
	  movie_ord int(10) unsigned NOT NULL,	# movie's order
	  PRIMARY KEY (actor_id,movie_id),
	)

Using best practices it means:

- the **table name** is a concatenation of the two related table names in lexicographic order, separated by an underscore (`'_'`);
- the **primary key** column names consist of the related table name followed by `'_id'`;
- the **order** column names consist of the related table name followed by `'_ord'`;

Of course it would be wise to add some indexes.

A concrete pivot record *has* to be derived  from **PivotRecord**. Two static functions *must* be defined in the derived class:

#### aClass() and bClass() ####

`protected static function aClass( )` 
`protected static function bClass( )` 

These static member functions should return the fully qualified class names of the related Models.

`aClass` and `bClass` are completely equivalent. **PivotRecord** is in any respect a symmetric class.

A complete definition of a pivot record might look like this:

	namespace app\models;
	use sjaakp\sortable\PivotRecord;

	class MovieActor extends PivotRecord    {

	    protected static function aClass( )   {
	        return Movie::className( );
	    }

	    protected static function bClass( )   {
	        return Actor::className( );
	    }
	}

Notice that you can define some other static values as well, for special cases. Refer to the source code if you need this.

A **PivotRecord**-derived class has the following extra functions.

#### getAs() and getBs() ####

`public static function getAs( ActiveRecord $b )` 

Get the ordered records of `classA` belonging to `classB $b`.

`public static function getBs( ActiveRecord $a )` 

Get the ordered records of `classB` belonging to `classA $a`.

The result is returned as an ActiveQuery, which can be modified further, or used as source of an ActiveDataProvider.

Notice these are **static** functions, not referring to any instantiation of the **PivotRecord**-derived class. 

#### orderA() and orderB() ####

`public function orderA( $newPosition )` 

Place `classA` at `$newPosition` in the list of all `classA`'s belonging to `classB`.

`public function orderB( $newPosition )` 

Place `classB` at `$newPosition` in the list of all `classB`'s belonging to `classA`.

These are **member** functions. The id's of `classA` and `classB` are stored in the current **PivotRecord**.


## MMSortable ##

This is a Behavior of both partner ActiveRecords in a many-to-many relations. **PivotRecord** relies on it. **MMSortable** performs some house keeping and has no (interesting) member functions. However, two properties *have* to be configured:

#### pivotClass ####

`string`. The fully classified class name of the  pivot class (the **PivotRecord**-derived class).

#### pivotIdAttr ####

`string`. The attribute name of the owner's id in the pivot class. If this is not set, it will be derived from the owner's class name; for instance: if the owner is class `Movie`, `$pivotIdAttr` will be `"movie_id"`.

----------

### Usage scenario 3  ###
## Many-to-many sorting ##

Apart from our `movie` table, we also have an `actor` table. They are linked via an `actor_movie` pivot table: each movie can have many actors, and each actor can have many movies.

First, we define a pivot class, like so: 

	namespace app\models;
	use sjaakp\sortable\PivotRecord;

	class MovieActor extends PivotRecord    {

	    protected static function aClass( )   {
	        return Movie::className( );
	    }

	    protected static function bClass( )   {
	        return Actor::className( );
	    }
	}

Then, we make sure that both `Movie` and `Actor` have a **MMSortable** Behavior:

	class Movie extends ActiveRecord	{
		...
	    public function behaviors( ) {
	        return [
	            [
	                'class' => 'sjaakp\sortable\MMSortable',
	                'pivotClass' => MovieActor::className( )
	            ]
	        ];
	    }
		...
	}

For convenience, we add a very simple member function to `Movie`:

	class Movie extends ActiveRecord	{
		...
	    public function getActors( ) {
	        return MovieActor::getBs( $this );
	    }
		...
	}

Define an `order-actor`-action in `MovieController`:

	class MovieController extends Controller
	{
		...
	    public function actionOrderActor( $id )   {
	        $post = Yii::$app->request->post( );
	        if (isset( $post['key'], $post['pos'] ))   {
	            $piv = MovieActor::find( )->where( [
	                'movie_id' => $id,
	                'actor_id' => $post['key']
	            ] )->one( );
	            $piv->orderB( $post['pos'] );
	        }
	    }
		...
	}

Now, in `movie/view`, we can display a **SortableGridView** with all the actors appearing in the movie.

	use sjaakp\sortable\SortableGridView;
	...
	$actors = new ActiveDataProvider( [
    	'query' => $model->getActors( ),
    	'sort' => false,
    	'pagination' => false
	] );
	...
    <h1><?= Html::encode( $model->title ) ?></h1>
	...
    <?= SortableGridView::widget( [
        'dataProvider' => $actors,
        'orderUrl' => ['order-actor', 'id' => $model->getPrimaryKey()],
        'columns' => [
			...
            'name:ntext',
			...
        ],
		...
    ] ); ?>

## Thanks ##

- **mike-kramer** (sortAxis option)
 