<?php
//source unknown for logic of showPageNumbers()
//modified by drale.com - 1-19-2010
//added showNext() and showPrev()

class Pagination {
  function getStartRow($page,$limit){
    $startrow = $page * $limit - ($limit);
    return $startrow;
  }
  function showPageNumbers($totalrows,$page,$limit){

    $pagination_links = null;
    /*
    PAGINATION SCRIPT
    seperates the list into pages
     */
    $numofpages = $totalrows / $limit;
    /* We divide our total amount of rows (for example 102) by the limit (25). This

  will yield 4.08, which we can round down to 4. In the next few lines, we'll
  create 4 pages, and then check to see if we have extra rows remaining for a 5th
    page. */

    for($i = 1; $i <= $numofpages; $i++){
    /* This for loop will add 1 to $i at the end of each pass until $i is greater
    than $numofpages (4.08). */

      if($i == $page){
        $pagination_links .= '<div class="page-link"><span>'.$i.'</span></div> ';
      }else{
        $pagination_links .= '<div class="page-link"><a href="?'.http_build_query(array('page' => $i) + $_GET,"","&amp;").'">'.$i.'</a></div> ';
      }
      /* This if statement will not make the current page number available in
      link form. It will, however, make all other pages available in link form. */
    }   // This ends the for loop

    if(($totalrows % $limit) != 0){
    /* The above statement is the key to knowing if there are remainders, and it's
    all because of the %. In PHP, C++, and other languages, the % is known as a
    Modulus. It returns the remainder after dividing two numbers. If there is no
    remainder, it returns zero. In our example, it will return 0.8 */

      if($i == $page){
        $pagination_links .= '<div class="page-link"><span>'.$i.'</span></div> ';
      }else{
        $pagination_links .= '<div class="page-link"><a href="?'.http_build_query(array('page' => $i) + $_GET,"","&amp;").'">'.$i.'</a></div> ';
      }
      /* This is the exact statement that turns pages into link form that is used above */
    }   // Ends the if statement

    return $pagination_links;
  }

  //added by drale.com - 1-19-2010
  function showNext($totalrows,$page,$limit,$text="next &raquo;"){
    $next_link = null;
    $numofpages = $totalrows / $limit;

    if($page < $numofpages){
      $page++;
      $next_link = '<div class="page-link"><a href="?'.http_build_query(array('page' => $page) + $_GET,"","&amp;").'">'.$text.'</a></div>';
    }

    return $next_link;
  }

  function showPrev($totalrows,$page,$limit,$text="&laquo; prev"){
    $next_link = null;
    $numofpages = $totalrows / $limit;

    if($page > 1){
      $page--;
      $prev_link = '<div class="page-link"><a href="?'.http_build_query(array('page' => $page) + $_GET,"","&amp;").'">'.$text.'</a></div>';
    }

    return $prev_link;
  }
}
?>
