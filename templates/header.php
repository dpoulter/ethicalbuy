<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Grocery Categories</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />


    
        
   
        
       
       <!--  <link href="/css/bootstrap.css" rel="stylesheet"/>
        <link href="/css/bootstrap-theme.min.css" rel="stylesheet"/>
        -->
       
        <!-- Bootstrap CSS 
    <link rel="stylesheet" href="/css/bootstrap.min.css" >
    <link href="/css/styles1.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.3/css/all.css" integrity="sha384-UHRtZLI+pbxtHCWp1t77Bi1L4ZtiqrqD80Kn4Z8NTSRyMA2Fd33n5dQ8lWUE00s/" crossorigin="anonymous">
    -->
 <!-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script> -->
     <script src="/js/jquery-3.3.1.min.js"></script>   
      <!--   <script src="/js/popper.js"></script>  -->
         <script src="/js/bootstrap.bundle.min.js"></script> 
         <script src="/js/bloodhound.min.js"></script> 
        <script src="/js/typeahead.jquery.js"></script> 
        <script src="/js/scripts.js"></script>  
  
        <?php if (isset($title)): ?>
            <title>Product Search <?= htmlspecialchars($title) ?></title>
        <?php else: ?>
            <title>Product Search</title>
        <?php endif ?>

        

</head>

    <body>
    
    <section class="jumbotron bg-white">
    
    <div class="container mt-5" >
    	 	
    	
    	
        
     <!--       <div id="middle" class="navigation">
              <ul class="nav nav-pills">
                 <li role="presentation" class="active"><a href="index.php">Home</a></li>
                <li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Portfolio<span class="caret"></span></a>
			<ul class="dropdown-menu" role="menu">
				<li><a href="performance.php">Overview</a></li>
				<li><a href="edit.php">Transactions</a></li>
				<li><a href="dividends.php">Dividends</a></li>
				<li><a href="topup.php">Deposit Cash</a></li>
				<li><a href="cash_history.php">Cash History</a></li>
			</ul>
		</li>
     -->
		<!--<li class="dropdown"><a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">Screens<span class="caret"></span></a>
          		<ul class="dropdown-menu" role="menu">
				<li><a href="screen_list.php">List Screens</a></li>
				</ul>
        </li>          		
		-->
        <!--
		<li role="presentation"><a href="logout.php">Log Out</a></li>
             </ul>
            </div>
        -->
          
  <!--          
<nav class="navbar navbar-expand-lg fixed-top navbar-dark bg-primary">
  <div class="collapse navbar-collapse" id="navbarNavDropdown">
    <ul class="navbar-nav">
      <li class="nav-item active">
        <a class="nav-link" href="index.php">Home <span class="sr-only">(current)</span></a>
      </li>
      
    </ul>
  </div>
        -->

  
 
</nav>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Ethical Buy</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu" 
      aria-controls="navbarMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarMenu">
      <div class="navbar-nav ms-auto">
        <a class="nav-link btn btn-outline-primary me-2" href="index.php">Home</a>
        <a class="nav-link btn btn-outline-primary me-2" href="about.php">About</a>
        <a class="nav-link btn btn-outline-primary me-2" href="search.php">Product Search</a>
        <a class="nav-link btn btn-outline-primary" href="contact.php">Contact</a>
      </div>
    </div>
  </div>
</nav>
