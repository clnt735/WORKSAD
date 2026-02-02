// Sidebar toggle
const menuToggle = document.getElementById("menu-toggle");
const sidebar = document.getElementById("sidebar");
const overlay = document.getElementById("overlay");
const closeBtn = document.getElementById("closeSidebar");

if (menuToggle && sidebar) {
  // Toggle sidebar when hamburger is clicked
  menuToggle.addEventListener("click", (e) => {
    e.stopPropagation(); // prevent click from bubbling to document
    sidebar.classList.toggle("active");
    if (overlay) overlay.classList.toggle("active"); // toggle overlay
  });

  // Close sidebar if clicked outside
  document.addEventListener("click", (e) => {
    if (
      sidebar.classList.contains("active") &&
        !sidebar.contains(e.target) &&
        !menuToggle.contains(e.target)
      )
       {
      sidebar.classList.remove("active");
    }
  });

   // Close sidebar when clicking overlay directly
  if (overlay) {
    overlay.addEventListener("click", () => {
      sidebar.classList.remove("active");
      overlay.classList.remove("active");
    });
  }


  // Close sidebar with ESC key
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && sidebar.classList.contains("active")) {
      sidebar.classList.remove("active");
      if (overlay) overlay.classList.remove("active"); // hide overlay
    }
  });
}


  // Close sidebar with x
  closeBtn.addEventListener("click", () => {
    sidebar.classList.remove("active");
    overlay.style.display = "none";
  });



  


  // Save scroll position before leaving/reloading

    window.addEventListener("beforeunload", function () {
      localStorage.setItem("scrollPosition", window.scrollY);
    });

  // Restore scroll position on load

    window.addEventListener("load", function () {
      const scrollPos = localStorage.getItem("scrollPosition");
      if (scrollPos) {
        window.scrollTo(0, scrollPos);
      }
    });

  
  // === Navbar hide/show on scroll + transparent on top === ///
  let lastScrollTop = 0;
  const navbar = document.querySelector('.navbar');

  function updateNavbar() {
    const currentScroll = window.pageYOffset || document.documentElement.scrollTop;

    // Hide navbar when scrolling down
    if (currentScroll > lastScrollTop && currentScroll > 100) {
      navbar.classList.add('hidden');
    } else {
      navbar.classList.remove('hidden');
    }

    // Change navbar style based on scroll position
    if (currentScroll <= 50) {
      navbar.classList.add('top');
      navbar.classList.remove('scrolled');
    } else {
      navbar.classList.remove('top');
      navbar.classList.add('scrolled');
    }

    lastScrollTop = Math.max(currentScroll, 0);
  }

  // Run on scroll
  window.addEventListener('scroll', updateNavbar);

  // ✅ Run once immediately on load
  window.addEventListener('load', updateNavbar);




  //=== Hamburger color change on top === //
  
  window.addEventListener("scroll", function () {
  const menuToggle = document.querySelector(".menu-toggle");

  if (window.scrollY <= 50) {
    // at the top
    menuToggle.classList.add("top");
  } else {
    // scrolled down
    menuToggle.classList.remove("top");
  }
});




  // === TOP EMPLOYERS === //

 const list = document.querySelector('.employers-list');
const leftBtn = document.querySelector('.scroll-btn.left');
const rightBtn = document.querySelector('.scroll-btn.right');
const pagination = document.querySelector('.pagination');
const cards = document.querySelectorAll('.employer-card');

// Variables
const cardWidth = cards[0].offsetWidth + 20; // card + gap
const cardsPerPage = 5;
const pageWidth = cardWidth * cardsPerPage;
const totalPages = Math.ceil(cards.length / cardsPerPage);

// Create pagination dots
for (let i = 0; i < totalPages; i++) {
  const dot = document.createElement("span");
  dot.classList.add("dot");
  if (i === 0) dot.classList.add("active");
  pagination.appendChild(dot);

  // Click on dot -> jump to that page
  dot.addEventListener("click", () => {
    list.scrollTo({ left: i * pageWidth, behavior: "smooth" });
  });
}

// Update arrows and dots
function updateUI() {
  const scrollLeft = list.scrollLeft;
  const maxScroll = list.scrollWidth - list.clientWidth;

  // hide left arrow if at start
  if (scrollLeft <= 0) {
    leftBtn.classList.add("hidden");
  } else {
    leftBtn.classList.remove("hidden");
  }

  // hide right arrow if at end
  if (scrollLeft >= maxScroll - 5) {
    rightBtn.classList.add("hidden");
  } else {
    rightBtn.classList.remove("hidden");
  }

  // Update active dot
  const pageIndex = Math.round(scrollLeft / pageWidth);
  document.querySelectorAll(".pagination .dot").forEach((dot, i) => {
    dot.classList.toggle("active", i === pageIndex);
  });
}

// Scroll events
rightBtn.addEventListener("click", () => {
  list.scrollBy({ left: pageWidth, behavior: "smooth" });
});
leftBtn.addEventListener("click", () => {
  list.scrollBy({ left: -pageWidth, behavior: "smooth" });
});
list.addEventListener("scroll", updateUI);

// Run on load
updateUI();













