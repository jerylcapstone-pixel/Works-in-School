<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to StyleSync</title>
        <link rel="stylesheet" href="about.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <style>
           .follow-me img {
                height: 20px;
                width: 30px;
                align-items: center; 
           }
           
           .contact-info img {
                height: 20px;
                width: 30px;
                align-items: center; 
           }
        
            .Contact {
                display: flex;
                justify-content: space-between;
            }
            
            .contact-info,
            .follow-me {
                flex: 1;
            }
            
            .contact-item,
            .social-link {
                margin-bottom: 10px; /* Add spacing between elements */
            }
            
            .social-link a {
                text-decoration: none;
                color: black;
                font-size: 30px;
            }
            
            @media screen and (max-width: 768px) {
                .Contact {
                    flex-direction: column; /* Display as columns on smaller screens */
                }
            
                .contact-info,
                .follow-me {
                    margin-bottom: 20px; /* Add margin between sections on smaller screens */
                }
                
                .fb {
                    font-size: 30px;
                    height: 20px;
                    width: 30px;
                    align-items: center; 
                }
            }
        </style>
    </head>
    <body>
    <div class="Admin">
        <nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
            <div class="container-fluid">
                <a class="navbar-brand" href="#"> <img src="logo.png" alt="Logo" style="max-height: 50px;">StyleSync</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#collapsibleNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="collapsibleNavbar">
                    <ul class="navbar-nav ms-auto me-0">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">Home</a>
                        </li>
                        <li class="nav-item active">
                            <a class="nav-link" href="#">About</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>
            <div class="Content">
                <div class="text">
                    <h1>About Us</h1>
                    <p>At StyleSync, we believe that everyone deserves to look and feel their best. Our team of talented stylists is dedicated to providing top-notch hair care services tailored to each client's unique style and preferences. With years of experience and a passion for creativity, we specialize in creating stunning looks that leave our clients feeling confident and beautiful. Convenience is key at StyleSync. That's why we've implemented an easy-to-use online appointment system, allowing you to book your next salon visit anytime, anywhere. Simply choose your preferred stylist, select your desired service, and schedule your appointment with just a few clicks. Say goodbye to waiting on hold and hello to hassle-free booking!</p>
                </div>
            </div>
    
            <div class="Contact" style="overflow-x: auto;">
                <div class="contact-info">
                    <h2>Contact ME</h2>
                    <div class="contact-details">
                        <img src="gmail.png" > <span style="text-decoration: none; color:black; font-size: 30px;">markjerylomandam@gmail.com</span> <br>
                        <img src="Location.png" > <span style="text-decoration: none; color:black; font-size: 30px;">Purok 2, Carangan, Ozamiz City, Misamis Occidental, 7200</span> <br>
                        <img src="phone.png" > <span style="text-decoration: none; color:black; font-size: 30px;">+63 9665734808</span>
                    </div>
                </div>
                <div class="follow-me">
                    <h2>Follow me on</h2>
                    <div class="social-links">
                        <img src="11.png" ><a href="https://www.facebook.com/markjeryl.herbito.2004" style="text-decoration: none; color:black; font-size: 30px;">Mark Jeryl Omandam</a> <br>
                        <img src="insta.png" ><a href="https://www.instagram.com/marrkeeeh/?hl=en"style="text-decoration: none; color:black; font-size: 30px;">@marrkeeeh</a>
                    </div>
                </div>
            </div>
    
            <div class="Footer">
                <p>&copy; 2024  StyleSync. All Rights Reserved.</p>
            </div>
        </div>
    </body>
    </html>
