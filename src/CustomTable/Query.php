<?php

namespace CustomTable;

class Query
{
    public $query;

    public $queryVars = array();

    public $queriedObject;

    public $queriedObjectId;

    public $request;

    public $results;

    public $resultCount = 0;

    public $currentResult = -1;

    public $inTheLoop = false;

    public $result;

    public $foundResults = 0;

    public $maxNumPages = 0;

    public function __construct( $query = '' ) {

        $this->init();

        $this->query = $this->queryVars = wp_parse_args( $query );

    }

    public function getFoundResults()
    {
        return $this->foundResults;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function setResult($result): void
    {
        $this->result = $result;
    }

    public function getResults()
    {
        return $this->results;
    }

    public function getResultCount()
    {
        return $this->resultCount;
    }

    public function setResultCount($resultCount): void
    {
        $this->resultCount = $resultCount;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function getQueryVars()
    {
        return $this->queryVars;
    }

    public function addQueryVar($queryVar, $value)
    {
        $this->queryVars[$queryVar] = $value;
    }

    public function init() {

        unset($this->results);
        unset($this->query);
        $this->queryVars = array();
        unset($this->queriedObject);
        unset($this->queriedObjectId);
        $this->resultCount = 0;
        $this->currentResult = -1;
        $this->inTheLoop = false;
        unset( $this->request );
        unset( $this->result );
        $this->foundResults = 0;
        $this->maxNumPages = 0;

    }

    public function set( $queryVar, $value ) {
        $this->addQueryVar($queryVar, $value);
    }

    public function getQueryResults() {

        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        $this->parseQuery();

        do_action_ref_array( 'custom_table_pre_get_results', array( &$this ) );

        // Shorthand.
        $q = &$this->queryVars;

        // Fill again in case pre_get_posts unset some vars.
        $q = $this->fillQueryVars($q);

        // Suppress filters
        if ( ! isset($q['suppress_filters']) )
            $q['suppress_filters'] = false;

        if ( empty( $q['items_per_page'] ) ) {
            $q['items_per_page'] = get_option( 'items_per_page', 20 );
        }

        // items_per_page
        $q['items_per_page'] = (int) $q['items_per_page'];

        if ( $q['items_per_page'] < -1 )
            $q['items_per_page'] = abs($q['items_per_page']);
        elseif ( $q['items_per_page'] == 0 )
            $q['items_per_page'] = 1;

        // page
        if ( isset($q['page']) ) {
            $q['page'] = trim($q['page'], '/');
            $q['page'] = absint($q['page']);
        }

        // If true, forcibly turns off SQL_CALC_FOUND_ROWS even when limits are present.
        if ( isset($q['no_found_rows']) )
            $q['no_found_rows'] = (bool) $q['no_found_rows'];
        else
            $q['no_found_rows'] = false;

        switch ( $q['fields'] ) {
            case 'ids':
                $fields = "{$databaseTable->getDatabase()->getTableName()}.{$databaseTable->getDatabase()->getPrimaryKey()}";
                break;
            default:
                $fields = "{$databaseTable->getDatabase()->getTableName()}.*";
        }

        // First let's clear some variables
        $distinct = '';
        $where = '';
        $limits = '';
        $join = '';
        $search = '';
        $groupby = '';
        $orderby = '';
        $page = 1;

        // If a search pattern is specified, load the posts that match.
        if ( strlen( $q['s'] ) ) {
            $search = $this->parseSearch( $q );
        }

        if ( ! $q['suppress_filters'] ) {
            $search = apply_filters_ref_array( 'custom_table_query_search', array( $search, &$this ) );
        }

        $where .= $search;

        // Rand order by
        $rand = ( isset( $q['orderby'] ) && 'rand' === $q['orderby'] );
        if ( ! isset( $q['order'] ) ) {
            $q['order'] = $rand ? '' : 'DESC';
        } else {
            $q['order'] = $rand ? '' : $this->parseOrder( $q['order'] );
        }

        // Order by.
        if ( empty( $q['orderby'] ) ) {
            if ( isset( $q['orderby'] ) && ( is_array( $q['orderby'] ) || false === $q['orderby'] ) ) {
                $orderby = '';
            } else {
                // Default order by primary key
                $orderby = "{$databaseTable->getDatabase()->getTableName()}.{$databaseTable->getDatabase()->getPrimaryKey()} " . $q['order'];
            }
        } elseif ( 'none' == $q['orderby'] ) {
            $orderby = '';
        } else {
            $orderby_array = array();
            if ( is_array( $q['orderby'] ) ) {
                foreach ( $q['orderby'] as $_orderby => $order ) {
                    $orderby = addslashes_gpc( urldecode( $_orderby ) );

                    $orderby_array[] = $orderby . ' ' . $this->parseOrder( $order );
                }
                $orderby = implode( ', ', $orderby_array );

            } else {
                $q['orderby'] = urldecode( $q['orderby'] );
                $q['orderby'] = addslashes_gpc( $q['orderby'] );

                foreach ( explode( ' ', $q['orderby'] ) as $i => $orderby ) {
                    $orderby_array[] = $orderby;
                }

                $orderby = implode( ' ' . $q['order'] . ', ', $orderby_array );

                if ( empty( $orderby ) ) {
                    $orderby = "{$databaseTable->getDatabase()->getTableName()}.{$databaseTable->getDatabase()->getPrimaryKey()} " . $q['order'];
                } elseif ( isset($q['order']) && ! empty( $q['order'] ) ) {
                    $orderby .= " {$q['order']}";
                }
            }
        }

        /*
         * Apply filters on where and join prior to paging so that any
         * manipulations to them are reflected in the paging by day queries.
         */
        if ( !$q['suppress_filters'] ) {
            $where = apply_filters_ref_array( 'custom_table_query_where', array( $where, &$this ) );
            $join = apply_filters_ref_array( 'custom_table_query_join', array( $join, &$this ) );
        }

        // Paging
        if ( empty($q['nopaging']) ) {
            $page = absint($q['paged']);
            if ( !$page )
                $page = 1;

            // If 'offset' is provided, it takes precedence over 'paged'.
            if ( isset( $q['offset'] ) && is_numeric( $q['offset'] ) ) {
                $q['offset'] = absint( $q['offset'] );
                $pgstrt = $q['offset'] . ', ';
            } else {
                $pgstrt = absint( ( $page - 1 ) * $q['items_per_page'] ) . ', ';
            }
            $limits = 'LIMIT ' . $pgstrt . $q['items_per_page'];
        }

        $pieces = array( 'where', 'groupby', 'join', 'orderby', 'distinct', 'fields', 'limits' );

        if ( !$q['suppress_filters'] ) {
            $where = apply_filters_ref_array( 'custom_table_query_where_paged', array( $where, &$this ) );

            $groupby = apply_filters_ref_array( 'custom_table_query_groupby', array( $groupby, &$this ) );

            $join = apply_filters_ref_array( 'custom_table_query_join_paged', array( $join, &$this ) );

            $orderby = apply_filters_ref_array( 'custom_table_query_orderby', array( $orderby, &$this ) );

            $distinct = apply_filters_ref_array( 'custom_table_query_distinct', array( $distinct, &$this ) );

            $limits = apply_filters_ref_array( 'custom_table_querylimits', array( $limits, &$this ) );

            $fields = apply_filters_ref_array( 'custom_table_query_fields', array( $fields, &$this ) );

            $clauses = (array) apply_filters_ref_array( 'custom_table_query_clauses', array( compact( $pieces ), &$this ) );

            $where = isset( $clauses[ 'where' ] ) ? $clauses[ 'where' ] : '';
            $groupby = isset( $clauses[ 'groupby' ] ) ? $clauses[ 'groupby' ] : '';
            $join = isset( $clauses[ 'join' ] ) ? $clauses[ 'join' ] : '';
            $orderby = isset( $clauses[ 'orderby' ] ) ? $clauses[ 'orderby' ] : '';
            $distinct = isset( $clauses[ 'distinct' ] ) ? $clauses[ 'distinct' ] : '';
            $fields = isset( $clauses[ 'fields' ] ) ? $clauses[ 'fields' ] : '';
            $limits = isset( $clauses[ 'limits' ] ) ? $clauses[ 'limits' ] : '';
        }

        do_action( 'custom_table_query_selection', $where . $groupby . $orderby . $limits . $join );

        if ( !$q['suppress_filters'] ) {
            $where = apply_filters_ref_array( 'custom_table_query_where_request', array( $where, &$this ) );

            $groupby = apply_filters_ref_array( 'custom_table_query_groupby_request', array( $groupby, &$this ) );

            $join = apply_filters_ref_array( 'custom_table_query_join_request', array( $join, &$this ) );

            $orderby = apply_filters_ref_array( 'custom_table_query_orderby_request', array( $orderby, &$this ) );

            $distinct = apply_filters_ref_array( 'custom_table_query_distinct_request', array( $distinct, &$this ) );

            $fields = apply_filters_ref_array( 'custom_table_query_fields_request', array( $fields, &$this ) );

            $limits = apply_filters_ref_array( 'custom_table_query_limits_request', array( $limits, &$this ) );

            $clauses = (array) apply_filters_ref_array( 'custom_table_query_clauses_request', array( compact( $pieces ), &$this ) );

            $where = isset( $clauses[ 'where' ] ) ? $clauses[ 'where' ] : '';
            $groupby = isset( $clauses[ 'groupby' ] ) ? $clauses[ 'groupby' ] : '';
            $join = isset( $clauses[ 'join' ] ) ? $clauses[ 'join' ] : '';
            $orderby = isset( $clauses[ 'orderby' ] ) ? $clauses[ 'orderby' ] : '';
            $distinct = isset( $clauses[ 'distinct' ] ) ? $clauses[ 'distinct' ] : '';
            $fields = isset( $clauses[ 'fields' ] ) ? $clauses[ 'fields' ] : '';
            $limits = isset( $clauses[ 'limits' ] ) ? $clauses[ 'limits' ] : '';
        }

        if ( ! empty($groupby) )
            $groupby = 'GROUP BY ' . $groupby;
        if ( !empty( $orderby ) )
            $orderby = 'ORDER BY ' . $orderby;

        $found_rows = '';
        if ( !$q['no_found_rows'] && !empty($limits) )
            $found_rows = 'SQL_CALC_FOUND_ROWS';

        $this->request = $old_request = "SELECT $found_rows $distinct $fields FROM {$databaseTable->getDatabase()->getTableName()} $join WHERE 1=1 $where $groupby $orderby $limits";

        if ( !$q['suppress_filters'] ) {

            $this->request = apply_filters_ref_array( 'custom_table_query_request', array( $this->request, &$this ) );
        }

        $this->results = apply_filters_ref_array( 'custom_table_results_pre_query', array( null, &$this ) );

        if ( 'ids' == $q['fields'] ) {
            if ( null === $this->getResults() ) {
                $this->results = $wpdb->get_col( $this->request );
            }

            $this->results = array_map( 'intval', $this->getResults() );
            $this->setResultCount( count( $this->getResults() ) );
            $this->setFoundResults( $q, $limits );

            return $this->results;
        }

        if ( null === $this->getResults() ) {
            $split_the_query = ( $old_request == $this->request && "{$databaseTable->getDatabase()->getTableName()}.*" == $fields && !empty( $limits ) && $q['items_per_page'] < 500 );

            $split_the_query = apply_filters( 'split_the_query', $split_the_query, $this );

            if ( $split_the_query ) {
                // First get the IDs and then fill in the objects

                $this->request = "SELECT $found_rows $distinct {$databaseTable->getDatabase()->getTableName()}.{$databaseTable->getDatabase()->getPrimaryKey()}} FROM {$databaseTable->getDatabase()->getTableName()} $join WHERE 1=1 $where $groupby $orderby $limits";

                $this->request = apply_filters( 'custom_table_query_request_ids', $this->request, $this );

                $ids = $wpdb->get_col( $this->request );

                if ( $ids ) {
                    $this->results = $ids;
                    $this->setFoundResults( $q, $limits );
                    // TODO: Add caching utility
                    //_prime_post_caches( $ids, $q['update_post_term_cache'], $q['update_post_meta_cache'] );
                } else {
                    $this->results = array();
                }
            } else {
                $this->results = $wpdb->get_results( $this->request );
                $this->setFoundResults( $q, $limits );
            }
        }

        // Convert to objects.
        if ( $this->getResults() ) {
            $this->setResult(array_map( 'custom_table_get_object', $this->getResults() ));
        }

        if ( ! $q['suppress_filters'] ) {
            $this->results = apply_filters_ref_array( 'custom_table_query_results', array( $this->getResults(), &$this ) );
        }

        if ( ! $q['suppress_filters'] ) {
            $this->results = apply_filters_ref_array( 'custom_table_the_results', array( $this->getResults(), &$this ) );
        }

        // Ensure that any posts added/modified via one of the filters above are
        // of the type WP_Post and are filtered.
        if ( $this->getResults() ) {
            $this->setResultCount(count( $this->getResults() ));

            $this->results = array_map( 'custom_table_get_object', $this->getResults() );

            //if ( $q['cache_results'] )
            //update_post_caches($this->posts, $post_type, $q['update_post_term_cache'], $q['update_post_meta_cache']);

            //$this->post = reset( $this->posts );
        } else {
            $this->setResultCount(0);
            $this->setResult(array());
        }

        return $this->results;

    }

    public function parseQuery( $query = '' ) {

        if ( ! empty( $query ) ) {
            $this->init();
            $this->query = $this->queryVars = wp_parse_args( $query );
        } elseif ( ! isset( $this->query ) ) {
            $this->query = $this->getQueryVars();
        }

        $this->queryVars = $this->fillQueryVars($this->queryVars);

        $qv = &$this->queryVars;

        $qv['paged'] = absint($qv['paged']);

        // Fairly insane upper bound for search string lengths.
        if ( isset($qv['s']) && ! is_scalar( $qv['s'] ) || ( isset($qv['s']) && ! empty( $qv['s'] ) && strlen( $qv['s'] ) > 1600 ) ) {
            $qv['s'] = '';
        }

        do_action_ref_array( 'custom_table_parse_query', array( &$this ) );

    }

    public function fillQueryVars($array) {
        $keys = array(
            'paged'
        , 's'
        , 'sentence'
        , 'fields'
        );

        foreach ( $keys as $key ) {
            if ( ! isset($array[$key]) )
                $array[$key] = '';
        }

        return $array;
    }

    protected function parseSearch( &$q ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        $search = '';

        $search_fields = apply_filters( "custom_table_query_{$databaseTable->getName()}_search_fields", array() );

        if( empty( $search_fields ) ) {
            return $search;
        }

        // added slashes screw with quote grouping when done early, so done later
        $q['s'] = stripslashes( $q['s'] );

        if ( empty( $_GET['s'] ) )
            $q['s'] = urldecode( $q['s'] );
        // there are no line breaks in <input /> fields
        $q['s'] = str_replace( array( "\r", "\n" ), '', $q['s'] );
        $q['search_terms_count'] = 1;
        if ( isset($q['sentence']) && ! empty( $q['sentence'] ) ) {
            $q['search_terms'] = array( $q['s'] );
        } else {
            if ( preg_match_all( '/".*?("|$)|((?<=[\t ",+])|^)[^\t ",+]+/', $q['s'], $matches ) ) {
                $q['search_terms_count'] = count( $matches[0] );
                $q['search_terms'] = $this->parseSearchTerms( $matches[0] );
                // if the search string has only short terms or stopwords, or is 10+ terms long, match it as sentence
                if ( empty( $q['search_terms'] ) || count( $q['search_terms'] ) > 9 )
                    $q['search_terms'] = array( $q['s'] );
            } else {
                $q['search_terms'] = array( $q['s'] );
            }
        }

        $n = isset($q['exact']) && ! empty( $q['exact'] ) ? '' : '%';
        $searchand = '';
        $q['search_orderby_title'] = array();

        $exclusion_prefix = apply_filters( 'custom_table_query_search_exclusion_prefix', '-' );

        foreach ( $q['search_terms'] as $term ) {
            // If there is an $exclusion_prefix, terms prefixed with it should be excluded.
            $exclude = $exclusion_prefix && ( $exclusion_prefix === substr( $term, 0, 1 ) );
            if ( $exclude ) {
                $like_op  = 'NOT LIKE';
                $andor_op = 'AND';
                $term     = substr( $term, 1 );
            } else {
                $like_op  = 'LIKE';
                $andor_op = 'OR';
            }

            if ( $n && ! $exclude ) {
                $like = '%' . $wpdb->esc_like( $term ) . '%';
                //$q['search_orderby_title'][] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $like );
            }

            $like = $n . $wpdb->esc_like( $term ) . $n;

            $search_fields_where = array();
            $search_fields_args = array();

            foreach( $search_fields as $search_field ) {
                $search_fields_where[] = "{$databaseTable->getDatabase()->getTableName()}.$search_field $like_op %s";
                $search_fields_args[] = $like;
            }

            $search_where = "(" . implode( ") $andor_op (", $search_fields_where ) . ")";

            $search .= $wpdb->prepare( "{$searchand}($search_where)", $search_fields_args );

            //$search .= $wpdb->prepare( "{$searchand}(({$wpdb->posts}.post_title $like_op %s) $andor_op ({$wpdb->posts}.post_excerpt $like_op %s) $andor_op ({$wpdb->posts}.post_content $like_op %s))", $like, $like, $like );

            $searchand = ' AND ';
        }

        if ( ! empty( $search ) ) {
            $search = " AND ({$search}) ";
        }

        return $search;
    }

    protected function parseSearchTerms( $terms ) {
        $strtolower = function_exists( 'mb_strtolower' ) ? 'mb_strtolower' : 'strtolower';
        $checked = array();

        $stopwords = $this->getSearchStopwords();

        foreach ( $terms as $term ) {
            // keep before/after spaces when term is for exact match
            if ( preg_match( '/^".+"$/', $term ) )
                $term = trim( $term, "\"'" );
            else
                $term = trim( $term, "\"' " );

            // Avoid single A-Z and single dashes.
            if ( ! $term || ( 1 === strlen( $term ) && preg_match( '/^[a-z\-]$/i', $term ) ) )
                continue;

            if ( in_array( call_user_func( $strtolower, $term ), $stopwords, true ) )
                continue;

            $checked[] = $term;
        }

        return $checked;
    }

    protected function getSearchStopWords() {
        if ( isset( $this->stopwords ) )
            return $this->stopwords;

        $words = explode( ',', _x( 'about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www',
            'Comma-separated list of search stopwords in your language' ) );

        $stopwords = array();
        foreach ( $words as $word ) {
            $word = trim( $word, "\r\n\t " );
            if ( $word )
                $stopwords[] = $word;
        }

        $this->stopwords = apply_filters( 'custom_table_search_stopwords', $stopwords );
        return $this->stopwords;
    }

    protected function parseOrder( $order ) {
        if ( ! is_string( $order ) || empty( $order ) ) {
            return 'DESC';
        }

        if ( 'ASC' === strtoupper( $order ) ) {
            return 'ASC';
        } else {
            return 'DESC';
        }
    }

    private function setFoundResults( $q, $limits ) {
        global $wpdb;
        // Bail if posts is an empty array. Continue if posts is an empty string,
        // null, or false to accommodate caching plugins that fill posts later.
        if ( $q['no_found_rows'] || ( is_array( $this->results ) && ! $this->results ) )
            return;

        if ( ! empty( $limits ) ) {
            $this->foundResults = $wpdb->get_var( apply_filters_ref_array( 'custom_table_found_results_query', array( 'SELECT FOUND_ROWS()', &$this ) ) );
        } else {
            $this->foundResults = count( $this->results );
        }

        $this->foundResults = apply_filters_ref_array( 'custom_table_found_results', array( $this->foundResults, &$this ) );

        if ( ! empty( $limits ) )
            $this->maxNumPages = ceil( $this->foundResults / $q['items_per_page'] );
    }

    public function query( $query ) {
        $this->init();
        $this->query = $this->queryVars = wp_parse_args( $query );
        return $this->getQueryResults();
    }
}