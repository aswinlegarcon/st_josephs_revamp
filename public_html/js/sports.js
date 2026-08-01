window.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.sports-card .col-md-4');

    cards.forEach(card => {
        card.addEventListener('mouseover', function() {
            cards.forEach(sibling => {
                if (sibling !== this) {
                    sibling.classList.add('blur');
                }
            });
        });

        card.addEventListener('mouseout', function() {
            cards.forEach(sibling => {
                sibling.classList.remove('blur');
            });
        });
    });

    window.addEventListener('scroll', function() {
        var reveals = document.querySelectorAll('.sports-card-reveal2,.sports-card-reveal3,.sports-card-reveal4,.sports-card-reveal5');
        for (var i = 0; i < reveals.length; i++) {
            var windowHeight = window.innerHeight;
            var revealTop = reveals[i].getBoundingClientRect().top;
            var revealPoint = 100;

            if (revealTop < windowHeight - revealPoint) {
                reveals[i].classList.add('active');
            } else {
                reveals[i].classList.remove('active');
            }
        }
    });
});
