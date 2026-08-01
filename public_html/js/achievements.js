window.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.achieve-card .col-md-6');

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
        var reveals = document.querySelectorAll('.achieve-card-reveal2,.achieve-card-reveal3,.achieve-card-reveal4,.achieve-card-reveal5');
        for (var i = 0; i < reveals.length; i++) {
            var windowHeight = window.innerHeight;
            var revealTop = reveals[i].getBoundingClientRect().top;
            var revealPoint = 150;

            if (revealTop < windowHeight - revealPoint) {
                reveals[i].classList.add('active');
            } else {
                reveals[i].classList.remove('active');
            }
        }
    });
});
