<?php

    // configuration
    require("../includes/config.php"); 

    // if form was submitted
    if ($_SERVER["REQUEST_METHOD"] == "POST")
    {
        // validate submission
        if (empty($_POST["search_string"]))
        {
            apologize("You haven't entered an search string.");
        }
       
                                  
           //increase cash
           
           //query("UPDATE users set cash=cash+? WHERE id=?",$_POST["amount"],$_SESSION["id"]);
           $results = search_brands($_POST["search_string"]);                
           // redirect to portfolio
           render("search_results.php", ["title" => "Search Results","results"=> $results]);
            
                  
    }      
    else
    {
        // else render Search form
        render("search_form.php", ["title" => "Search Products"]);
    }

?>
