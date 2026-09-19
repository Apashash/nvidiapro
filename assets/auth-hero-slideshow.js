(function () {
  const heroes = document.querySelectorAll('[data-auth-hero]');

  heroes.forEach((hero) => {
    const slides = Array.from(hero.querySelectorAll('.hero-slide'));
    if (slides.length < 2) return;

    let activeIndex = 0;
    slides[0].classList.add('active');

    window.setInterval(() => {
      slides[activeIndex].classList.remove('active');
      activeIndex = (activeIndex + 1) % slides.length;
      slides[activeIndex].classList.add('active');
    }, 3000);
  });
})();