
rollmean <- function(day, obs, cutoff = 90) {
  obs <- as.matrix(obs)
  answer <- matrix(nrow = 0, ncol = ncol(obs))
  if (nrow(obs) > 1) {
    wt <- lapply(2:nrow(obs), function(i)
      pmax(0, cutoff - day[i] + day[1:(i-1)] + 1))
    wt.obs <- lapply(1:(nrow(obs)-1), FUN =
      function(i)
        if(sum(wt[[i]]) > 0) {
          apply(obs[1:i, , drop = F] * wt[[i]], 2, sum) / sum(wt[[i]])
        } else {
          rep(NA, ncol(obs))
        }
    )
    answer <- do.call(rbind, wt.obs)
  }
  answer <- rbind(rep(NA, ncol(obs)), answer)
  if (!is.null(dimnames(answer)))
    dimnames(answer)[[2]] <- paste("wt", dimnames(answer)[[2]], sep = "_")
  return(answer)
}