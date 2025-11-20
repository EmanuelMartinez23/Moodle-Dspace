document.querySelectorAll('.toggle').forEach(item => {
    item.addEventListener('click', () => {
        const nestedList = item.nextElementSibling; 
        const arrow = item.previousElementSibling; 

        if (nestedList && nestedList.classList.contains("nested")) { 
            nestedList.style.display = nestedList.style.display === "block" ? "none" : "block";
            
            arrow.style.transform = nestedList.style.display === "block" ? "rotate(90deg)" : "rotate(0deg)";
        }
    });
});